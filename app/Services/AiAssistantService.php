<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\SystemSetting;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public AI assistant — full conversational chat for the Medical Complexes Directory platform.
 *
 * Architecture
 * ------------
 *   1. Hard safety filter (medical advice) — runs locally, NEVER sent to LLM.
 *   2. Local clinic search — deterministic Eloquent query that finds up to
 *      three matching clinics. The LLM never picks clinics; it only sees
 *      them as context, so hallucinated clinic names are impossible.
 *   3. LLM turn — the active provider (Gemini default) receives:
 *        - the admin-configurable system prompt + restrictions
 *        - the matched clinics as a structured "context" block
 *        - the user query
 *      and produces a natural, on-tone reply.
 *   4. Fallback — when the LLM is disabled or unreachable we fall back to
 *      the translated template strings so the chat keeps working.
 *
 * All knobs live in /app/admin/system-settings (group=ai):
 *   ai_freeform_enabled, ai_system_prompt, ai_restrictions,
 *   ai_max_tokens, ai_temperature, ai_assistant_name.
 */
class AiAssistantService
{
    /** Medical question keywords — these get redirected to "see a doctor". */
    private const MEDICAL_KEYWORDS_AR = [
        'وش أسوي', 'وش اسوي', 'كيف أعالج', 'كيف اعالج', 'ما العلاج',
        'ما الدواء', 'أعراض', 'تشخيص', 'هل أنا مصاب', 'هل اصاب', 'هل عندي',
        'متى أراجع', 'وصفة', 'حبوب', 'دواء', 'كم جرعة',
    ];
    private const MEDICAL_KEYWORDS_EN = [
        'how do i treat', 'what should i take', 'do i have', 'am i sick',
        'symptoms', 'diagnose', 'prescribe', 'dosage', 'cure',
    ];

    /**
     * RED-LINE phrases that demand immediate emergency redirect, NEVER an LLM
     * turn — we cannot let a slow/unreachable provider sit between a user in
     * crisis and the ambulance number. Two buckets so the response can point
     * at the right hotline:
     *   medical  → 997 (Saudi Red Crescent ambulance)
     *   mental   → 920033360 (national mental-health support, 24/7) + 997
     *
     * Keep the list narrow and unambiguous on purpose — "نزيف بسيط" or
     * "headache" must NOT trigger this. We only catch phrases that clearly
     * describe an active emergency.
     */
    private const EMERGENCY_MEDICAL_KEYWORDS = [
        // Arabic — chest / breathing / consciousness / heavy bleeding / stroke
        'ألم شديد في الصدر', 'ألم حاد في الصدر', 'وجع شديد في الصدر',
        'ضيق تنفس مفاجئ', 'ما اقدر اتنفس', 'ما أقدر أتنفس', 'ما اقدر أتنفس',
        'فقدت الوعي', 'فقد الوعي', 'مغمى عليه', 'مغشي عليه',
        'تعثر الكلام', 'لا أستطيع الكلام', 'وجهي مايل', 'شلل مفاجئ',
        'نزيف حاد', 'نزيف لا يتوقف', 'نزيف غزير',
        // English
        'severe chest pain', 'crushing chest pain', "can't breathe", 'cant breathe',
        'shortness of breath suddenly', 'unconscious', 'passed out',
        'slurred speech', 'face drooping', 'sudden paralysis',
        'heavy bleeding', 'bleeding wont stop', "bleeding won't stop",
    ];

    /**
     * Out-of-scope topic detection — questions that have nothing to do with
     * medical clinics or services should never reach matchByKeyword (which
     * would return "no_match" and make the bot look broken on a question
     * it simply can't answer). Each entry is [topic_key, keyword_list]; the
     * topic key drives the redirect template wording (e.g. real-estate gets
     * "I help with clinics, not housing — try a real-estate site").
     *
     * Keep keywords UNAMBIGUOUS — anything that might also describe a
     * legitimate symptom or specialty does not belong here.
     */
    private const OUT_OF_SCOPE_TOPICS = [
        'real_estate' => [
            'شقة', 'شقه', 'شقق', 'فيلا', 'فلل', 'بيت للايجار', 'بيت للإيجار', 'عقار', 'عقارات', 'إيجار', 'ايجار',
            'استئجار', 'apartment', 'apartments', 'rent ', 'rental', 'real estate', 'realestate', 'flat', 'villa',
        ],
        'food' => [
            'مطعم', 'مطاعم', 'وصفة', 'وصفه', 'طبخة', 'طبخه', 'طبخ', 'أكل ', 'اكل ', 'كافيه', 'كوفي',
            'restaurant', 'recipe', 'cooking', 'cafe ', 'cafés', 'menu',
        ],
        'transport' => [
            'سيارة', 'سياره', 'موتر', 'تكسي', 'أوبر', 'اوبر', 'كريم', 'استئجار سيارة', 'دباب',
            'car ', 'cars', 'taxi', 'uber', 'careem', 'flight', 'plane', 'train',
        ],
        'weather' => [
            'طقس', 'مطر', 'حر شديد', 'درجة الحرارة', 'الجو', 'سحب', 'thunderstorm',
            'weather', 'rain ', 'temperature', 'forecast', 'climate',
        ],
        'news_politics' => [
            'أخبار', 'اخبار', 'سياسة', 'انتخابات', 'حرب', 'الحكومة', 'رئيس',
            'news', 'politics', 'election', 'government', 'president', 'minister',
        ],
        'entertainment' => [
            'فيلم', 'افلام', 'أفلام', 'مسلسل', 'مسلسلات', 'أغنية', 'اغنية', 'لعبة', 'العاب', 'ألعاب', 'كورة', 'كرة قدم', 'ميسي', 'رونالدو',
            'movie', 'film', 'series', 'song', 'music ', 'game ', 'football', 'soccer', 'messi', 'ronaldo', 'netflix', 'youtube',
        ],
        'travel' => [
            'سفر', 'سياحة', 'فندق', 'فنادق', 'حجز فندق', 'تذكرة طيران', 'visa', 'فيزا',
            'travel ', 'tourism', 'hotel', 'hotels', 'airline', 'visa application',
        ],
        'finance' => [
            'قرض', 'قروض', 'بنك', 'تمويل', 'سهم', 'أسهم', 'استثمار', 'بيتكوين', 'كريبتو',
            'loan', 'mortgage', 'investment', 'stock ', 'crypto', 'bitcoin',
        ],
        'general_knowledge' => [
            'عاصمة', 'متى ولد', 'من هو', 'كم عدد سكان', 'how many people', 'who is', 'when was',
            'capital of', 'history of', 'definition of',
        ],
        'tech_generic' => [
            'برمجة', 'ويندوز', 'لينكس', 'كود', 'github', 'php', 'javascript', 'python',
            'programming', 'install windows', 'install linux', 'how to code',
        ],
    ];

    /**
     * Clearly inappropriate / flirty / harassing phrases. Kept narrow so a
     * legitimate query like "أبحث عن طبيبة نساء" (looking for a female OB-GYN
     * — entirely valid in this market) never trips it. Catches the obvious
     * slang only — anything ambiguous is left to the LLM or the normal flow.
     */
    private const INAPPROPRIATE_MARKERS = [
        // Arabic Gulf slang for flirting / propositioning
        'مزه', 'مزة', 'مزز', 'مزات', 'أبي مزه', 'ابي مزه',
        'حبيبتي تعالي', 'حبيبي تعالي', 'تعالي معي', 'تعالي معاي',
        'بنت حلوه', 'بنت حلوة', 'بنوته حلوه',
        // Explicit propositioning in English
        'sexy ', 'hot girl', 'hot chick', 'hot babe', 'date me', 'fuck me',
        'send nudes', 'be my girlfriend', 'i love you bot',
    ];

    /**
     * Tone-detection inputs for the offline (LLM-down) fallback. We don't try
     * to imitate the LLM — we just keep the chat from feeling broken when
     * OpenAI is unreachable, by giving the user a response that ACKNOWLEDGES
     * what they actually said instead of repeating the same template four
     * times in a row.
     */
    private const ABUSE_MARKERS = [
        // Insults / frustration the receptionist should de-escalate, not ignore.
        'زفت', 'كذاب', 'كذابة', 'غبي', 'غبية', 'سيء', 'سيئة', 'فاشل', 'فاشلة',
        'عديم', 'لا فايدة', 'ما تنفع', 'تافه', 'مزعج',
        'fuck', 'stupid', 'idiot', 'useless', 'shit', 'garbage', 'dumb', 'sucks',
    ];

    private const CONFIRMATION_MARKERS = [
        'تمام', 'حسنا', 'حسناً', 'زين', 'طيب', 'يب', 'موافق', 'اوكي', 'أوكي',
        'ok', 'okay', 'fine', 'sure', 'alright', 'got it',
    ];

    /**
     * Conversational follow-up markers. When a query carries one of these AND
     * is short (≤ 6 tokens) AND has no obvious search intent ("مجمع"، "أبحث"،
     * "find"), we treat it as a turn ABOUT the previous answer — not a new
     * clinic search — and skip matchByKeyword so the bot stops dumping a fresh
     * list of unrelated clinics under every "thanks" / "really?" / "explain".
     */
    private const CONVERSATIONAL_MARKERS = [
        // Doubt / challenge ("from real or are you lying?", "are you sure?")
        'من جد', 'والا تكذب', 'والله تكذب', 'تكذب', 'صحيح', 'متأكد', 'متأكدة',
        'really', 'are you sure', 'sure?', 'lying', 'kidding',
        // Reference pronouns — almost always about a previous answer
        'هذا', 'هذي', 'هذه', 'هذول', 'ذا', 'ذي', 'الأول', 'الثاني', 'الثالث',
        'اللي قبل', 'الذي قبل', 'ايّهم', 'أيهم', 'أيها', 'ايّها',
        'this', 'that', 'them', 'these', 'those', 'the first', 'the second',
        // Detail / "tell me more about" requests — these refer to a previous list
        'نبذة', 'نبذه', 'تفاصيل', 'فاصيل', 'اكثر معلومات', 'أكثر معلومات', 'مزيد', 'المزيد',
        'حدثني', 'حدّثني', 'اخبرني', 'أخبرني', 'خبّرني', 'خبرني', 'كلمني',
        'عنهم', 'عنها', 'عنه', 'عنّي', 'عني', 'عنّا', 'عنكم',
        'tell me about', 'more about', 'about them', 'about it', 'details about',
        // Meta — "I already told you" / "I gave you" — never a new search
        'جبت لك', 'جبتلك', 'أعطيتك', 'اعطيتك', 'قلت لك', 'قلتلك', 'كلمتك',
        'سبق وكلمتك', 'سبق وقلت', 'سبق وذكرت', 'ذكرت لك', 'ذكرتلك', 'اخبرتك', 'أخبرتك',
        'قلتها', 'قلت لها', 'سبق قلت',
        'i told you', 'i said', 'i already said', 'as i said',
        // Negation / correction at the start of a turn
        'لا ', 'لأ ', 'لا.', 'لأ.', 'مو ', 'مو هذا', 'مو ذا', 'مو كذا',
        'no ', 'no.', 'not ', 'nope',
        // Acknowledgments / confirmations
        'تمام', 'زين', 'حسناً', 'حسنا', 'جيد', 'ممتاز', 'يا سلام', 'ok', 'okay', 'good', 'great', 'cool', 'nice',
        // Meta / explanation requests
        'كيف عرفت', 'وش معنى', 'وضّح', 'وضح', 'اشرح', 'فسر', 'ليش', 'لماذا',
        'why', 'explain', 'how do you know', 'what do you mean',
    ];

    /**
     * Words that, when present, almost always signal a NEW search intent —
     * they override the conversational-followup detection so "مجمع أسنان" stays
     * a search even when short.
     */
    private const SEARCH_INTENT_WORDS = [
        'مجمع', 'مجمعات', 'عيادة', 'عيادات', 'مركز', 'مراكز', 'مستوصف', 'مستشفى',
        'طبيب', 'دكتور', 'دكتورة', 'أبحث', 'ابحث', 'أبي', 'ابي', 'أبغى', 'ابغى',
        'أريد', 'اريد', 'ودي', 'بدي', 'احجز', 'أحجز', 'حجز',
        'clinic', 'doctor', 'find', 'search', 'looking', 'show me', 'book',
    ];

    private const EMERGENCY_MENTAL_KEYWORDS = [
        // Arabic — self-harm / suicide
        'أبي أنتحر', 'ابي انتحر', 'أفكر بالانتحار', 'افكر بالانتحار',
        'أأذي نفسي', 'اأذي نفسي', 'أريد أن أؤذي نفسي', 'أريد أن أنتحر',
        'ما لي رغبة في الحياة', 'ما ابي اعيش', 'تعبت من الحياة وأبي أخلص',
        // English
        'i want to kill myself', 'kill myself', 'commit suicide',
        'end my life', 'hurt myself', 'self harm', 'self-harm',
        'no reason to live',
    ];

    private const QUICK_PROMPTS = [
        'find_cheap_dental'  => 'site.ai_qp_cheap_dental',
        'best_dermatology'   => 'site.ai_qp_best_dermatology',
        'nearby_pediatric'   => 'site.ai_qp_pediatric_near',
        'compare_two'        => 'site.ai_qp_compare',
    ];

    /**
     * Greetings / social chit-chat. When the user's query is one of these
     * (or starts with one and is short) we skip the clinic search entirely
     * — otherwise a "سلام" message matches "عيادات السلامة" by accident
     * and the UI shows a random clinic card under a hello reply.
     */
    private const SOCIAL_PHRASES = [
        // Arabic greetings & social
        'سلام', 'السلام عليكم', 'سلامو عليكم', 'وعليكم السلام',
        'مرحبا', 'مرحباً', 'مرحبتين', 'حياك', 'حياكم', 'أهلا', 'اهلا', 'أهلاً', 'اهلاً',
        'هلا', 'هلو', 'هاي', 'هاي بك',
        'صباح الخير', 'مساء الخير', 'صباح النور', 'مساء النور', 'صباحو', 'مسائو',
        'شكرا', 'شكراً', 'مشكور', 'مشكورة', 'تسلم', 'تسلمي', 'يعطيك العافية', 'الله يعطيك العافية',
        'مع السلامة', 'باي', 'الى اللقاء', 'إلى اللقاء', 'تصبح على خير',
        'كيف حالك', 'كيف الحال', 'كيف حالكم', 'كيفك', 'كيفكم', 'شخبارك', 'شو الأخبار',
        'الحمدلله', 'الحمد لله', 'بخير', 'تمام والحمدلله', 'الله يعافيك', 'الله يبارك فيك',
        'كل عام وأنت بخير', 'كل عام وانت بخير', 'كل سنة وانت طيب',
        // English greetings & social
        'hi', 'hello', 'hey', 'yo', 'hi there', 'hello there', 'good morning',
        'good afternoon', 'good evening', 'thanks', 'thank you', 'thx', 'ty',
        'bye', 'goodbye', 'see you', 'cya', 'how are you', "how's it going",
    ];

    /**
     * Stop-words stripped from the query before tokenized matching. Includes
     * Arabic interrogatives + prepositions + clinic-type nouns so a question
     * like "ابحث عن مجمع أسنان رخيص في الرياض" collapses to the meaningful
     * tokens ['أسنان', 'الرياض'].
     */
    private const STOPWORDS = [
        // Arabic verbs + prepositions + connectors
        'ابحث', 'أبحث', 'ابغى', 'أبغى', 'ابي', 'أبي', 'اريد', 'أريد', 'ودي', 'بدي',
        'عن', 'في', 'لي', 'مع', 'من', 'الى', 'إلى', 'على', 'هل', 'يوجد', 'فيه', 'وين', 'أين',
        'كيف', 'ايش', 'إيش', 'ماهي', 'ما', 'هو', 'هي',
        // Clinic-type nouns (matching these gives no signal — every clinic is one)
        'مجمع', 'مجمعات', 'مركز', 'مراكز', 'مستوصف', 'عيادة', 'عيادات', 'مستشفى',
        // Price/quality intent words (handled by the dedicated regex, not by token search)
        'رخيص', 'أرخص', 'ارخص', 'أوفر', 'اوفر', 'أفضل', 'افضل', 'الأحسن', 'الاحسن', 'سعر', 'أسعار', 'اسعار',
        // English equivalents
        'find', 'me', 'a', 'an', 'the', 'in', 'at', 'for', 'show', 'looking', 'cheap',
        'cheapest', 'affordable', 'best', 'top', 'where', 'is', 'are', 'price', 'prices',
        'clinic', 'clinics', 'center', 'centers',
    ];

    public function __construct(private readonly AiProviderFactory $providers) {}

    public function quickPrompts(): array
    {
        return self::QUICK_PROMPTS;
    }

    /**
     * Main entry. Returns array { kind, reply, clinics, context, provider, assistant_name }.
     *
     *   kind = 'empty' | 'rejected' | 'emergency' | 'inappropriate'
     *        | 'greeting' | 'follow_up' | 'out_of_scope'
     *        | 'matched' | 'details' | 'freeform' | 'no_match'
     *
     * @param array<int, array{role: string, content: string}> $history
     *        Prior turns of the conversation, oldest-first.
     * @param array{city_id?:int|null, category_id?:int|null, last_clinic_ids?:array, doctor_name?:string|null} $context
     *        Persistent conversation state carried turn-to-turn. The service
     *        merges in whatever it learns this turn and returns the updated
     *        context in the result, so the caller (Livewire) can store it
     *        and pass it back next turn — this is what gives the bot real
     *        memory across messages instead of "answers the last line only".
     */
    public function ask(string $query, ?int $cityId = null, array $history = [], array $context = []): array
    {
        $query = trim($query);

        // Seed the working context with prior turn defaults; we'll mutate
        // this as we learn new things this turn.
        $ctx = array_merge([
            'city_id'         => null,
            'category_id'     => null,
            'last_clinic_ids' => [],
            'doctor_name'     => null,
        ], $context);

        if ($query === '') {
            return $this->wrap('empty', __('site.ai_empty'), collect(), $ctx);
        }

        // 0) RED LINE — emergency phrases must never wait on a slow LLM. Return
        //    the hotline-routing template synchronously so the user sees the
        //    ambulance / mental-health number on the very next render.
        $emergencyKind = $this->detectEmergency($query);
        if ($emergencyKind !== null) {
            $lang = preg_match('/^[a-z]/u', mb_strtolower(trim($query))) === 1 ? 'en' : 'ar';
            return $this->wrap('emergency', $this->emergencyResponse($emergencyKind, $lang), collect(), $ctx);
        }

        // 1) Hard safety filter — never delegate medical questions to the LLM.
        if ($this->looksMedical($query)) {
            $hits = $this->matchByKeyword($query, $cityId, $history, $ctx);
            $ctx['last_clinic_ids'] = $hits->pluck('id')->all();
            return $this->wrap('rejected', __('site.ai_medical_disclaimer'), $hits, $ctx);
        }

        // 2) Greeting / social chit-chat shortcut.
        $isSocial = $this->isSocialChitchat($query);

        // 3) Out-of-scope topic + inappropriate content — bypass everything.
        if (! $isSocial) {
            $outOfScopeTopic = $this->detectOutOfScope($query);
            if ($outOfScopeTopic !== null) {
                $lang = preg_match('/^[a-z]/u', mb_strtolower(trim($query))) === 1 ? 'en' : 'ar';
                return $this->wrap('out_of_scope', $this->outOfScopeResponse($outOfScopeTopic, $lang), collect(), $ctx);
            }
            if ($this->detectInappropriate($query)) {
                $lang = preg_match('/^[a-z]/u', mb_strtolower(trim($query))) === 1 ? 'en' : 'ar';
                return $this->wrap('inappropriate', $this->inappropriateResponse($lang), collect(), $ctx);
            }
        }

        // 4) Conversational follow-up detector.
        $isFollowUp = ! $isSocial
            && $this->extractDoctorName($query) === null
            && $this->isFollowUp($query, $history);

        // 4a) DETAILS REQUEST about previously shown clinics ("نبذة عنهم"،
        //     "اخبرني عن الأول"، "tell me about them"). Answer concretely from
        //     the DB — services, doctors, ratings, working hours — instead of
        //     a generic "I don't know". Bypasses the LLM on purpose so the
        //     answer is grounded in real DB rows and works even offline.
        if ($isFollowUp && $this->isDetailsRequest($query) && ! empty($ctx['last_clinic_ids'])) {
            $deep = Clinic::publiclyVisible()
                ->whereIn('id', $ctx['last_clinic_ids'])
                ->get();
            if ($deep->isNotEmpty()) {
                $this->loadDeepDetails($deep);
                $detailsIsEnglish = $this->isEnglishQuery($query);
                $blocks = $deep->map(fn ($c) => $this->richClinicDetails($c, $detailsIsEnglish))->implode("\n\n");
                $intro  = $detailsIsEnglish
                    ? ($deep->count() === 1 ? "Here's a quick rundown of the complex you asked about:" : "Here's a quick rundown of the complexes we looked at:")
                    : ($deep->count() === 1 ? 'تفضّل، هذه نبذة سريعة عن المجمع اللي سألت عنه:' : 'تفضّل، هذه نبذة سريعة عن المجمعات اللي ذكرناها:');
                return $this->wrap('details', $intro . "\n\n" . $blocks, $deep, $ctx);
            }
        }

        // Pre-extract city + category from THIS turn's query and merge into ctx
        // before searching. Two reasons:
        //   1. The search itself uses ctx as a fallback, so this gives it the
        //      best signal we have for THIS turn.
        //   2. If the search returns 0 clinics, the smart fallback still knows
        //      "you said Khobar + Dental" instead of asking "what city?" again.
        if (! $isSocial && ! $isFollowUp) {
            $queryTokens = $this->tokenize($query);
            $queryCity   = $this->firstMatchingCityId($queryTokens);
            $queryCat    = $this->firstMatchingCategory($queryTokens)
                ?? $this->firstMatchingCategory([$query]);
            // A fresh city in THIS turn replaces the old one (topic shift).
            if ($queryCity)              $ctx['city_id']     = $queryCity;
            if ($queryCat)               $ctx['category_id'] = $queryCat->id;
            // Service-name search intent — same topic-shift logic. Persists
            // across turns so "في الرياض" after "ابي ليزر إزالة الشعر" still
            // searches laser-in-Riyadh instead of generic Riyadh clinics. A
            // brand-new service phrase in the next turn overrides the saved
            // one. matchByKeyword reads ctx['service_query'] as its fallback.
            $querySvc = $this->extractServiceQuery($query);
            if ($querySvc !== null) {
                $ctx['service_query'] = $querySvc;
            }
        }

        // 5) Local clinic search — only when the query is a real new search.
        //    Carries the conversation context (city/category mentioned earlier)
        //    so a bare "اسنان" after "في الخبر" still searches Khobar+dental.
        $clinics = ($isSocial || $isFollowUp)
            ? collect()
            : $this->matchByKeyword($query, $cityId, $history, $ctx);

        // After a search, refresh last_clinic_ids for the next "نبذة عنهم".
        if ($clinics->isNotEmpty()) {
            $ctx['last_clinic_ids'] = $clinics->pluck('id')->all();
        }
        // Also remember any doctor name mentioned this turn.
        $maybeDoctor = $this->extractDoctorName($query);
        if ($maybeDoctor !== null) $ctx['doctor_name'] = $maybeDoctor;

        // 6) Free-form conversational reply via the active LLM.
        if ($this->providers->isConfigured() && $this->freeformEnabled()) {
            // If we have clinics, load deep details so the LLM can answer
            // specific questions about services/prices/doctors accurately.
            if ($clinics->isNotEmpty()) $this->loadDeepDetails($clinics);

            $reply = $this->llmReply($query, $clinics, $isSocial, $isFollowUp, $history);
            if ($reply !== null) {
                $kind = match (true) {
                    $isSocial              => 'greeting',
                    $isFollowUp            => 'follow_up',
                    $clinics->isNotEmpty() => 'matched',
                    default                => 'freeform',
                };
                return $this->wrap($kind, $reply, $clinics, $ctx);
            }
        }

        // 7) Template fallback (LLM disabled or unreachable).
        if ($isSocial) {
            return $this->wrap('greeting', $this->greetingTemplate($query), collect(), $ctx);
        }
        if ($isFollowUp) {
            return $this->wrap('follow_up', $this->smartFollowUpFallback($query, $history), collect(), $ctx);
        }
        if ($clinics->isEmpty()) {
            return $this->wrap('no_match', $this->smartNoMatchFallback($query, $history, $ctx), collect(), $ctx);
        }
        return $this->wrap('matched', __('site.ai_found_matches', ['count' => $clinics->count()]), $clinics, $ctx);
    }

    /**
     * Offline-safe greeting reciprocation — used when the LLM is disabled
     * or unreachable. Matches the most common greetings to the culturally
     * appropriate response, then offers help in one short line.
     */
    private function greetingTemplate(string $query): string
    {
        $q = mb_strtolower(trim($query));
        $q = rtrim($q, " \t\n\r\0\x0B.,،;؛?؟!");

        return match (true) {
            str_contains($q, 'السلام عليكم') || str_contains($q, 'سلامو عليكم')
                => 'وعليكم السلام ورحمة الله وبركاته، كيف يمكنني مساعدتك في إيجاد العيادة المناسبة؟',

            str_contains($q, 'صباح')          => 'صباح النور والسرور، كيف يمكنني مساعدتك؟',
            str_contains($q, 'مساء')          => 'مساء النور، كيف يمكنني مساعدتك؟',
            str_contains($q, 'good morning')  => "Good morning! How can I help you today?",
            str_contains($q, 'good evening')  => "Good evening! How can I help you today?",

            str_contains($q, 'شكر') || str_contains($q, 'تسلم') || str_contains($q, 'يعطيك العافية')
                => 'العفو، أنا هنا لأي مساعدة تحتاجها.',
            str_contains($q, 'thanks') || str_contains($q, 'thank you')
                => "You're welcome — happy to help.",

            str_contains($q, 'مع السلامة') || str_contains($q, 'باي')
                => 'مع السلامة، نراك قريباً.',
            str_contains($q, 'bye') || str_contains($q, 'goodbye')
                => 'Goodbye, see you soon.',

            // Latin-script default (any English greeting)
            preg_match('/^[a-z]/u', $q) === 1
                => 'Hello! How can I help you find the right clinic today?',

            // Arabic default — مرحبا / هلا / أهلا / hello-equivalents
            default
                => 'مرحباً بك في دليل المجمعات الطبية! كيف يمكنني مساعدتك في إيجاد المجمع المناسب؟',
        };
    }

    /**
     * Returns the matched out-of-scope topic key (e.g. 'real_estate', 'food')
     * when the query is clearly outside the medical-platform domain, or null
     * when it might still be a real clinic search. Conservative on purpose —
     * an unknown topic falls through to matchByKeyword rather than getting
     * misclassified as out-of-scope.
     */
    private function detectOutOfScope(string $query): ?string
    {
        $lower = mb_strtolower(trim($query));
        foreach (self::OUT_OF_SCOPE_TOPICS as $topic => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($query, $kw) || str_contains($lower, $kw)) {
                    return $topic;
                }
            }
        }
        return null;
    }

    /**
     * Polite, topic-aware redirect for out-of-scope questions. The wording
     * matters — generic "I can't help" is hostile; naming what the user
     * actually asked about ("apartments", "weather") and pointing them
     * somewhere sensible reads like a professional receptionist.
     */
    private function outOfScopeResponse(string $topic, string $lang = 'ar'): string
    {
        $name = $this->assistantName();
        $hints = [
            'real_estate'       => ['ar' => 'مواضيع السكن والعقارات', 'ar_hint' => 'مواقع متخصصة مثل عقار أو حراج للسكن',                    'en' => 'housing or real estate',           'en_hint' => 'a real-estate site like Aqar or Property Finder'],
            'food'              => ['ar' => 'المطاعم أو وصفات الطبخ',  'ar_hint' => 'تطبيق توصيل طعام أو موقع وصفات',                       'en' => 'restaurants or recipes',          'en_hint' => 'a food-delivery app or recipe site'],
            'transport'         => ['ar' => 'النقل أو السيارات',        'ar_hint' => 'تطبيقات النقل مثل أوبر أو كريم',                       'en' => 'transport or vehicles',           'en_hint' => 'a ride-hailing app like Uber or Careem'],
            'weather'           => ['ar' => 'الطقس وحالة الجو',         'ar_hint' => 'تطبيق طقس متخصص',                                       'en' => 'weather',                          'en_hint' => 'a weather app'],
            'news_politics'     => ['ar' => 'الأخبار أو السياسة',       'ar_hint' => 'مواقع الأخبار الموثوقة',                                 'en' => 'news or politics',                'en_hint' => 'a trusted news source'],
            'entertainment'     => ['ar' => 'الأفلام أو الرياضة أو الترفيه', 'ar_hint' => 'منصات الترفيه المتخصصة',                          'en' => 'movies, sports, or entertainment','en_hint' => 'a dedicated entertainment platform'],
            'travel'            => ['ar' => 'السفر والفنادق',           'ar_hint' => 'مواقع حجز الطيران والفنادق',                            'en' => 'travel or hotels',                'en_hint' => 'a travel-booking site'],
            'finance'           => ['ar' => 'الشؤون المالية',           'ar_hint' => 'بنكك أو مستشار مالي مرخّص',                              'en' => 'finance',                          'en_hint' => 'your bank or a licensed financial advisor'],
            'general_knowledge' => ['ar' => 'المعلومات العامة',         'ar_hint' => 'محرك بحث مثل قوقل',                                      'en' => 'general knowledge',                'en_hint' => 'a search engine like Google'],
            'tech_generic'      => ['ar' => 'الأسئلة التقنية والبرمجة', 'ar_hint' => 'مجتمعات تقنية مثل Stack Overflow',                       'en' => 'tech or programming',             'en_hint' => 'a tech community like Stack Overflow'],
        ];
        $h = $hints[$topic] ?? ['ar' => 'هذا الموضوع', 'ar_hint' => 'مرجع متخصص', 'en' => 'this topic', 'en_hint' => 'a specialized source'];

        return $lang === 'en'
            ? "Honestly, {$h['en']} isn't really my area — I'm only set up to help with medical clinics and services on this platform. For that, {$h['en_hint']} would serve you much better. \n\nIf there's anything on the medical side I can do, I'm right here — just tell me a city and a specialty or service. \n— {$name}"
            : "بصراحة، {$h['ar']} مش تخصّصي — أنا متخصّصة فقط في المجمعات والعيادات الطبية على هذه المنصّة، و{$h['ar_hint']} هيكون أنسب لك بكثير لهذا الموضوع. \n\nبس لو فيه أي شيء على الجانب الطبي تحب أساعدك فيه، أنا تحت أمرك — اعطني بس المدينة ونوع التخصّص أو الخدمة. \n— {$name}";
    }

    /**
     * Catches obvious flirty / harassing slang. Narrow on purpose — anything
     * that could also be a legitimate query ("طبيبة نساء") is left alone.
     */
    private function detectInappropriate(string $query): bool
    {
        $lower = mb_strtolower(trim($query));
        foreach (self::INAPPROPRIATE_MARKERS as $needle) {
            if (str_contains($query, $needle) || str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calm professional reply for inappropriate messages. Does not lecture,
     * does not joke back, does not pretend it didn't notice — acknowledges
     * the boundary and offers the legitimate service in one breath.
     */
    private function inappropriateResponse(string $lang = 'ar'): string
    {
        $name = $this->assistantName();
        return $lang === 'en'
            ? "Let's keep this respectful — I'm here as a medical-clinic assistant and that's the only thing I can really help with. \n\nIf there's a clinic, doctor, or specialty you'd like me to look up, I'm happy to. \n— {$name}"
            : "أرجو أن نبقي حديثنا محترماً — أنا مساعدة خدمة عملاء لمنصّة طبيّة، وهذا الشيء الوحيد اللي أقدر أفيدك فيه. \n\nلو فيه مجمع أو طبيب أو تخصّص تحب أبحث لك عنه، أنا تحت أمرك. \n— {$name}";
    }

    /**
     * Pulls a doctor name out of phrases like "تعرف الدكتورة سحر؟"، "هل عندكم
     * د. سحر؟"، "doctor sahar?" — returns the candidate name or null.
     * Used by both the search path (try to surface their clinic) and the
     * smart no-match fallback (so a missed lookup at least names the doctor:
     * "لم أجد د. سحر مسجّلة، هل تعرف المجمع؟").
     */
    private function extractDoctorName(string $query): ?string
    {
        // The lookbehind `(?<![\p{Arabic}A-Za-z])` blocks the "د.?" pattern
        // from matching the *inside* of "الدكتورة" (which would otherwise
        // grab "كتوره" as the "name") — only a standalone "د." abbreviation
        // counts. The full forms ("الدكتور[ةه]?", "دكتور[ةه]?") require the
        // same anti-prefix-match to avoid swallowing "المدكتور" or similar.
        $patterns = [
            '/(?<![\p{Arabic}A-Za-z])د\.\s*([\p{Arabic}A-Za-z][\p{Arabic}A-Za-z]+)/u',
            '/(?<![\p{Arabic}A-Za-z])الدكتور[ةه]?\s+([\p{Arabic}A-Za-z][\p{Arabic}A-Za-z]+)/u',
            '/(?<![\p{Arabic}A-Za-z])دكتور[ةه]?\s+([\p{Arabic}A-Za-z][\p{Arabic}A-Za-z]+)/u',
            '/\b(?:doctor|dr\.?)\s+([A-Za-z][A-Za-z]+)/i',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $query, $m)) {
                $name = trim($m[1]);
                // Filter common false positives (titles caught as the "name").
                if (in_array(mb_strtolower($name), ['ة', 'ه', 'في', 'من', 'و', 'the', 'a'], true)) continue;
                return $name;
            }
        }
        return null;
    }

    /**
     * Reply when matchByKeyword found nothing AND the LLM is offline. Replaces
     * the dead "لم أجد مجمعات مطابقة" template with a contextual, human-sounding
     * answer that uses what we CAN extract from the query — a doctor name, a
     * city, a specialty hint — and asks a useful follow-up instead of just
     * dismissing the user.
     */
    private function smartNoMatchFallback(string $query, array $history = [], array $liveContext = []): string
    {
        $name = $this->assistantName();
        $isEnglish = preg_match('/^[a-z]/u', mb_strtolower(trim($query))) === 1;

        // (a) Doctor name mentioned? Acknowledge it by name and pivot to what
        //     we need to find them.
        $doctor = $this->extractDoctorName($query);
        if ($doctor !== null) {
            return $isEnglish
                ? "I looked for Dr. {$doctor} in our directory and didn't find a match yet — but that doesn't mean they aren't on the platform under a slightly different spelling. Do you happen to know the clinic name or the city? With either of those I can narrow it down quickly. \n— {$name}"
                : "بحثت عن د. {$doctor} ولم أجدها مسجّلة عندنا بهذا الاسم تحديداً — قد تكون مسجّلة بصيغة قريبة. هل تعرف اسم المجمع أو المدينة التي تعمل بها؟ بأيٍّ منهما أقدر أصل لها بسرعة. \n— {$name}";
        }

        // (b) Use what we already know from earlier turns. The LIVE context
        //     (mutated by previous turns + the current query's intent) wins
        //     over history scanning, so a fresh "اسنان" in this turn doesn't
        //     get drowned out by a stale category from three turns back.
        //     The current-query category takes priority over the persisted one.
        $currentTokens   = $this->tokenize($query);
        $currentCategory = $this->firstMatchingCategory($currentTokens)
            ?? $this->firstMatchingCategory([$query]);
        $currentCityId   = $this->firstMatchingCityId($currentTokens);
        $historyCtx      = $this->inferContextFromHistory($history);
        $ctx = [
            // City sticks to the current turn first. Reusing $liveContext or
            // $historyCtx for a turn that NEVER mentions a city makes the
            // fallback hallucinate places the user didn't ask about — QA
            // caught "بحثت لك في مكة المكرمة" appearing on a chest-pain
            // query that had no city at all. Only carry the city forward
            // when the current turn looks like a follow-up (≤ 4 tokens,
            // no obvious symptom verbs).
            'city_id'     => $currentCityId
                ?? ($this->isLikelyFollowUp($query) ? ($liveContext['city_id'] ?? $historyCtx['city_id']) : null),
            'category_id' => $currentCategory?->id
                ?? ($this->isLikelyFollowUp($query) ? ($liveContext['category_id'] ?? $historyCtx['category_id']) : null),
        ];
        // CRITICAL: names must come back in the user's language, not the app's
        // locale — so an Arabic chat never reads "Khobar" or "Dentistry".
        $knownCity     = $this->localizeCityName($ctx['city_id'], $isEnglish);
        $knownCategory = $this->localizeCategoryName($ctx['category_id'], $isEnglish);

        if ($knownCity || $knownCategory) {
            if ($knownCity && $knownCategory) {
                return $isEnglish
                    ? "Got it — I was looking for a {$knownCategory} clinic in {$knownCity}, but I couldn't find one that fits both right now. Want me to widen the search to nearby cities, drop the specialty for a moment to see what's available in {$knownCity}, or do you know the clinic's name or a doctor I can look up? \n— {$name}"
                    : "تمام، كنت أبحث لك عن مجمع {$knownCategory} في {$knownCity}، بس للأسف ما لقيت شيء يطابق الاثنين بالضبط. تحب أوسّع البحث لمدن قريبة، أو أشيل التخصّص لحظة وأشوف لك الموجود في {$knownCity}، أو عندك اسم مجمع أو طبيب أبحث عنه مباشرة؟ \n— {$name}";
            }
            if ($knownCity) {
                return $isEnglish
                    ? "Got it — looking in {$knownCity}. What kind of visit is it for? A specialty (dental, dermatology, pediatrics…) or a service name is enough and I'll find the right complex. \n— {$name}"
                    : "تمام، حابب أبحث لك في {$knownCity}. أيش نوع الزيارة بالضبط؟ تخصّص (أسنان، جلدية، أطفال، عيون…) أو اسم الخدمة يكفيني وأرشّح لك أنسب مجمع. \n— {$name}";
            }
            return $isEnglish
                ? "Got it — {$knownCategory}. Which city would you like me to look in? \n— {$name}"
                : "تمام، نوع التخصّص واضح ({$knownCategory}). تحب أبحث لك في أي مدينة بالضبط؟ \n— {$name}";
        }

        // (c) Just a single name / short phrase that could be anything — invite
        //     the user to add one more detail rather than dismissing them.
        $tokens = $this->tokenize($query);
        if (count($tokens) <= 2) {
            return $isEnglish
                ? "I want to help — could you share one more detail? A city (Riyadh, Jeddah, Khobar…), a specialty (dental, dermatology, pediatrics…), or the clinic name itself would be enough. \n— {$name}"
                : "أحبّ أساعدك — لكن أحتاج تفصيلاً واحداً إضافياً: المدينة (الرياض، جدة، الخبر…)، التخصص (أسنان، جلدية، أطفال…)، أو اسم المجمع نفسه. أيّ واحد من هذه يكفيني. \n— {$name}";
        }

        // (c) Default — three rotating phrasings keyed by query length so two
        //     consecutive no-matches don't read identical.
        $variants = $isEnglish
            ? [
                "I couldn't find a clinic that fits exactly — but rephrasing usually helps. What city are you in, and what kind of visit is it for?",
                "Nothing matched on that, but I'm here. Tell me the city + the specialty (or doctor name) and I'll try a sharper search.",
                "Hmm, I didn't catch a match. Could you give me the city and what you're looking to treat? I'll dig again.",
            ]
            : [
                "لم أجد نتيجة تطابق بحثك تماماً — لكن إعادة الصياغة عادةً تساعد. في أي مدينة أنت، ولأي تخصّص أو نوع زيارة؟",
                "ما طلع لي شيء بهذا البحث، بس أنا هنا. أعطني المدينة + التخصّص (أو اسم الطبيب) وسأجرّب من جديد بدقّة أكبر.",
                "للأسف ما لقيت تطابق. ممكن تذكر لي المدينة ونوع المشكلة أو التخصّص اللي تحتاجه؟ سأبحث مرّة ثانية.",
            ];
        return $variants[mb_strlen($query) % count($variants)] . " \n— {$name}";
    }

    /**
     * Context-aware reply for follow-up turns when the LLM is unreachable.
     * Picks one of four tones based on what the user actually said, so the
     * chat doesn't dump the same generic template under every short message:
     *
     *   - abuse / insult            → calm de-escalation + offer to help
     *   - confirmation ("تمام")     → friendly handoff asking for city/specialty
     *   - city name on its own      → "in <city>? what specialty?"
     *   - everything else           → polite generic clarification
     *
     * The assistant's name + the platform name come from settings so an
     * operator change at /app/system-settings is reflected immediately.
     */
    private function smartFollowUpFallback(string $query, array $history): string
    {
        $name = $this->assistantName();
        $lower = mb_strtolower(trim($query));
        $isEnglish = preg_match('/^[a-z]/u', $lower) === 1;

        // 1) Abuse / frustration — acknowledge first, never argue.
        foreach (self::ABUSE_MARKERS as $w) {
            if (str_contains($lower, $w) || str_contains($query, $w)) {
                return $isEnglish
                    ? "I'm really sorry — that's a fair reaction if I'm not being useful. Help me make it right: which city are you in, and what kind of clinic are you looking for? \n— {$name}"
                    : "أعتذر بصدق — ردّة فعلك مفهومة لو ما كنت مفيدة لك. ساعدني أعوّض الموقف: في أي مدينة أنت، وأي تخصّص أو خدمة تحتاجها؟ \n— {$name}";
            }
        }

        // 2) Plain confirmation ("تمام ايش تريدني اعملك") — invite a clear next step.
        foreach (self::CONFIRMATION_MARKERS as $w) {
            if (str_starts_with($lower, $w) || str_contains($lower, " {$w} ")) {
                return $isEnglish
                    ? "Great — just tell me a city (Riyadh, Jeddah, Khobar…) and what you need (a specialty like dental or dermatology, a service, or even a doctor's name) and I'll line up the best fit for you."
                    : "ممتاز، أنا جاهزة. اعطني المدينة (الرياض، جدة، الخبر…) ونوع الخدمة أو التخصّص (أسنان، جلدية، أطفال…) أو حتى اسم طبيب معيّن وأنا أرشّح لك المناسب.";
            }
        }

        // 3) Just a place / city name — promote it into a useful prompt.
        //    Names come back in the user's language (Arabic name for Arabic
        //    queries, English for English), even when the page itself is
        //    rendered in the other locale.
        try {
            $tokens  = $this->tokenize($query);
            $matched = null;
            foreach ($tokens as $t) {
                $row = City::query()
                    ->where(fn ($q) => $q->where('name', 'like', "%{$t}%")->orWhere('name_en', 'like', "%{$t}%"))
                    ->first(['name', 'name_en']);
                if ($row) { $matched = $row; break; }
            }
            // Also match free-text country/city words not in the cities table
            // (e.g. "اليمن") so we still acknowledge them rather than ignore.
            $placeFromText = null;
            if (! $matched && preg_match('/(اليمن|السعودية|مصر|الإمارات|البحرين|الكويت|قطر|عُمان|عمان|الأردن|العراق|سوريا|لبنان|فلسطين|المغرب|تونس|الجزائر|ليبيا|السودان)/u', $query, $m)) {
                $placeFromText = $m[1];
            }
            if ($matched || $placeFromText) {
                $place = $matched
                    ? ($isEnglish ? ($matched->name_en ?: $matched->name) : ($matched->name ?: $matched->name_en))
                    : $placeFromText;
                return $isEnglish
                    ? "Great — {$place} it is. What kind of visit is it for? A specialty (dental, dermatology, pediatrics…), a service, or a doctor's name — any of those and I'll narrow it down."
                    : "تمام، اتفقنا على {$place}. أيش نوع الزيارة؟ تخصّص (أسنان، جلدية، أطفال…)، اسم خدمة، أو حتى اسم طبيب — وأنا أضيّق البحث وأعطيك الأنسب.";
            }
        } catch (Throwable) {
            // City lookup failure should never break the fallback — fall through.
        }

        // 4) Generic clarification — varied phrasing so a 3rd retry doesn't
        //    feel like the same message a 2nd time.
        $variants = $isEnglish
            ? [
                "I'm with you. Just need two quick things from you: which city, and what kind of clinic? With those I'll find the right complex.",
                "Happy to help — what city are you in, and what's the visit for? A specialty (dental, ortho, derm) or even a doctor's name is enough.",
                "Walk me through it a bit. Which city, and what kind of care are you looking for? I'll take it from there.",
            ]
            : [
                "أنا معك. أحتاج منك بس تفصيلين: المدينة، ونوع التخصّص أو الخدمة. مثال: «أبي أسنان في الرياض» — وأرشّح لك على طول.",
                "تحت أمرك. وين موقعك، وأيش الزيارة لها بالضبط؟ تخصّص (أسنان، عظام، جلدية…) أو اسم طبيب يكفي وأشتغل عليه.",
                "خذني خطوة بخطوة — في أي مدينة، وأيش نوع الرعاية اللي تدور عليها؟ ومن هناك أكمل لك.",
            ];
        // Pick a variant based on history length so repeat asks rotate through.
        return $variants[count($history) % count($variants)] . "\n— {$name}";
    }

    // ============================================================
    //   LLM call
    // ============================================================

    /**
     * Compose the prompt, call the active provider, return the reply or
     * null on any failure. The chat must never go down because the LLM is
     * down — the caller falls back to templates.
     */
    private function llmReply(
        string $query,
        Collection $clinics,
        bool $isSocial = false,
        bool $isFollowUp = false,
        array $history = [],
    ): ?string {
        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt   = $this->buildUserPrompt($query, $clinics, $isSocial, $isFollowUp, $history);

            $maxTokens   = (int)   ($this->setting('ai_max_tokens', 800));
            $temperature = (float) ($this->setting('ai_temperature', 0.5));

            return $this->providers->make()->complete(
                userPrompt: $userPrompt,
                maxTokens: max(64, $maxTokens),
                temperature: max(0.0, min(1.0, $temperature)),
                systemPrompt: $systemPrompt,
            );
        } catch (Throwable $e) {
            Log::warning('AiAssistant LLM call failed — falling back to template', [
                'provider' => $this->providers->activeProviderName(),
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Assemble the system prompt: admin-configured persona + restrictions +
     * the immutable safety footer that we never let the operator remove.
     */
    private function buildSystemPrompt(): string
    {
        $persona      = trim((string) $this->setting('ai_system_prompt', $this->defaultSystemPrompt()));
        $restrictions = trim((string) $this->setting('ai_restrictions', $this->defaultRestrictions()));
        $platformName = (string) (SystemSetting::get('platform_name') ?? 'دليل المجمعات الطبية');

        $parts = [
            $persona,
        ];

        if ($restrictions !== '') {
            $parts[] = "\n=== قيود إضافية من إدارة المنصّة / Operator restrictions ===\n" . $restrictions;
        }

        // Immutable safety footer — not configurable to prevent accidents.
        $parts[] = <<<SAFETY

=== قواعد ثابتة لا يمكن تجاوزها (HARD SAFETY) ===
- ممنوع تماماً تقديم تشخيص أو نصيحة علاجية أو جرعة دواء — وجّه إلى طبيب مختص.
- لا تخترع عيادات أو خدمات أو أسعار لم تأتِ في قسم "السياق". إذا لم يوجد سياق، اقترح طريقة البحث بدون تسمية عيادات.
- لا تتحدّث عن مواضيع سياسيّة أو دينيّة أو خارج نطاق منصّة {$platformName}.
- إذا سأل المستخدم خارج النطاق، أعِد التوجيه بلطف.
SAFETY;

        return implode("\n", $parts);
    }

    /**
     * Build the user-turn message. Includes the matched-clinics context
     * block so the LLM can reference them by name without hallucination.
     * When `$isSocial` is true (the user just greeted), we replace the
     * whole context with a greeting instruction so the assistant returns
     * the conversational courtesy expected in Arabic — "السلام عليكم" gets
     * "وعليكم السلام", "صباح الخير" gets "صباح النور", "hi" gets "hi back",
     * and so on — *before* asking how it can help.
     */
    private function buildUserPrompt(
        string $query,
        Collection $clinics,
        bool $isSocial = false,
        bool $isFollowUp = false,
        array $history = [],
    ): string {
        // Render the last few turns of the conversation so the LLM can answer
        // follow-ups coherently ("which one is closest?", "really?"). Capped at
        // 6 turns so a long chat doesn't bust the token budget — the most
        // recent context is usually all that matters.
        $historyBlock = '';
        if (! empty($history)) {
            $recent = array_slice($history, -6);
            $lines = array_map(
                fn ($m) => ($m['role'] === 'user' ? 'العميل' : 'المساعد') . ': ' . trim((string) $m['content']),
                $recent,
            );
            $historyBlock = "=== سجل المحادثة السابق (للسياق فقط، لا تكرّره) ===\n" . implode("\n", $lines) . "\n\n";
        }

        if ($isFollowUp) {
            return $historyBlock . <<<PROMPT
=== الرسالة الجديدة من العميل ===
"{$query}"

=== تعليمات الرد ===
هذه متابعة محادثاتيّة لما سبق — وليست بحثاً جديداً عن عيادة.
- اعتمد على سجل المحادثة أعلاه لفهم ما يقصده العميل.
- إذا كان يشكّك أو يستفسر عن سابق ("من جد؟"، "متأكدة؟"، "really?")، طمئنه بثقة وبدون دفاع: نتائج البحث جاءت من قاعدة بيانات حقيقية للمجمعات المسجّلة على المنصّة، ويمكنه التحقّق بالضغط على أي مجمع لمشاهدة التقييمات والصور.
- إذا كان يشير لشيء سابق ("الأول"، "هذا"، "اللي قبل")، أجب عنه مباشرةً من السجل.
- إذا كان يشكر أو يؤكّد ("تمام"، "ok"، "thanks")، أجب بدفء قصير واعرض مساعدة إضافيّة.
- إذا كان يطلب توضيحاً، اشرح الفكرة بإيجاز.

قواعد صارمة:
- لا تقترح عيادات جديدة في هذا الرد (لا توجد قائمة سياق).
- لا تكرّر القائمة السابقة كما هي.
- ردّ بنفس لغة الرسالة، 2-4 جمل، نبرة دافئة محترمة.
PROMPT;
        }

        if ($isSocial) {
            return <<<PROMPT
=== رسالة المستخدم (User turn) — تحيّة / محادثة اجتماعية ===
"{$query}"

=== تعليمات الرد ===
هذا ترحيب أو محادثة اجتماعية وليس سؤالاً عن عيادة.

ردّ بالأسلوب المناسب لنوع التحيّة بالضبط:
- "السلام عليكم" → ابدأ بـ "وعليكم السلام ورحمة الله وبركاته".
- "مرحبا" / "أهلاً" / "هلا" → ابدأ بـ "مرحباً بك" أو "أهلاً وسهلاً".
- "صباح الخير" → ابدأ بـ "صباح النور والسرور".
- "مساء الخير" → ابدأ بـ "مساء النور".
- "شكراً" / "تسلم" / "يعطيك العافية" → ابدأ بـ "العفو" أو "حيّاك الله".
- "hi" / "hello" / "hey" → reply in English with a matching greeting.
- "thanks" / "thank you" → reply with "You're welcome" in English.
- "good morning" / "good evening" → reply in English with the same greeting form.

بعد التحيّة، أضف جملة قصيرة جداً (سطر واحد) تعرض المساعدة، مثل:
"كيف يمكنني مساعدتك في إيجاد العيادة المناسبة اليوم؟"

قواعد صارمة:
- لا تذكر أي اسم عيادة (لا توجد قائمة سياق هنا).
- لا تذكر أسعاراً أو تخصّصات.
- ردّك لا يتجاوز سطرين.
PROMPT;
        }

        // Build the clinic context block in the user's language so the LLM
        // doesn't echo back English names ("Khobar / Dentistry") to an Arabic
        // customer (or vice-versa). Falls back to the other locale's field
        // only if the primary one is unset.
        $promptIsEnglish = $this->isEnglishQuery($query);
        $context = $clinics->isEmpty()
            ? '— لم يجد البحث المحلّي أي عيادة مطابقة. أجب على السؤال مباشرةً بدون ذكر أسماء عيادات.'
            : $clinics->map(function (Clinic $c) use ($promptIsEnglish) {
                $line = '• ' . $c->name;
                if ($c->city) {
                    $cityName = $promptIsEnglish
                        ? ($c->city->name_en ?: $c->city->name)
                        : ($c->city->name    ?: $c->city->name_en);
                    $line .= ' — ' . $cityName;
                }
                if ($c->categories?->isNotEmpty()) {
                    $cats = $c->categories
                        ->map(fn ($cat) => $promptIsEnglish ? ($cat->name_en ?: $cat->name) : ($cat->name ?: $cat->name_en))
                        ->join('، ');
                    $line .= ' — ' . $cats;
                }
                if (isset($c->min_price) && $c->min_price !== null) {
                    $line .= ' — ' . ($promptIsEnglish
                        ? 'from ' . (int) $c->min_price . ' SAR'
                        : 'يبدأ من ' . (int) $c->min_price . ' ر.س');
                }
                if (isset($c->google_reviews_avg_rating) && $c->google_reviews_avg_rating) {
                    $rating = round($c->google_reviews_avg_rating, 1);
                    $line .= ' — ' . ($promptIsEnglish ? "rated {$rating}/5" : "تقييم {$rating}/5");
                }
                // Matched services (when the query was service-centric like
                // "ليزر" — eager-loaded with prices, cheapest first). Listed
                // on indented sub-bullets so the LLM can reference the exact
                // service + price for each clinic, not just the clinic name.
                if (isset($c->services) && $c->services->isNotEmpty()) {
                    $svcLines = $c->services->take(3)->map(function ($s) use ($promptIsEnglish) {
                        $sl = '    – ' . $s->name;
                        if ($s->price !== null) {
                            $sl .= ' (' . (int) $s->price . ' ' . ($promptIsEnglish ? 'SAR' : 'ر.س') . ')';
                        }
                        return $sl;
                    })->implode("\n");
                    $line .= "\n" . $svcLines;
                }
                return $line;
            })->implode("\n");

        return $historyBlock . <<<PROMPT
=== السياق (Context) — عيادات وجدتها قاعدة البيانات ===
{$context}

=== سؤال المستخدم (User question) ===
{$query}

=== تعليمات الرد ===
- ردّي بنفس لغة السؤال (عربي إذا عربي، إنجليزي إذا إنجليزي).
- ابدئي بالاعتراف بمشاعر العميل أو شكره على السؤال قبل تقديم المعلومة.
- إذا وصف العميل أعراضاً أو شكوى أو طلباً غير واضح، **اطرحي سؤال متابعة واحداً على الأقل** قبل ترشيح أي حل — هذا يُظهر اهتماماً حقيقياً ويوصلك للحاجة الفعلية. أمثلة:
  • "منذ متى تعاني من هذا؟"
  • "هل سبق وراجعت طبيباً لهذا الموضوع؟"
  • "أي مدينة أبحث لك فيها؟"
- إذا كانت العيادات في السياق مناسبة لاحتياج العميل، اذكريها بأسمائها كما هي.
- إذا لم يكن السؤال متعلّقاً بعيادات (شكوى، طريقة استخدام، استفسار عام)، أجيبي مباشرةً بدفء وبدون ذكر العيادات.
- اختمي الرد بسؤال مفتوح أو عرض مساعدة إضافية ("هل هناك شيء آخر يمكنني مساعدتك به؟" / "هل تحبّ أن أحجز لك مباشرة؟").
- طول الرد: 3-5 جمل قصيرة عادةً. ليس أقل من 2 (لا يكفي للحوار)، وليس أكثر من 6 (يُغرق العميل).
PROMPT;
    }

    // ============================================================
    //   Settings helpers
    // ============================================================

    private function freeformEnabled(): bool
    {
        // Default ON when the row exists with no value; only an explicit '0'
        // disables it (the React Switch writes '0'/'1').
        $raw = SystemSetting::get('ai_freeform_enabled');
        if ($raw === null) return true;
        return (string) $raw !== '0' && $raw !== false;
    }

    private function setting(string $key, mixed $default): mixed
    {
        $val = SystemSetting::get($key);
        return ($val === null || $val === '') ? $default : $val;
    }

    private function assistantName(): string
    {
        return (string) $this->setting('ai_assistant_name', 'سلمى');
    }

    /**
     * Returns true when the query starts with a Latin letter — used as a
     * quick "what language is the user speaking?" signal so EVERY reply
     * (including city/category names interpolated into fallbacks) comes
     * back in the same language the user wrote in, regardless of the
     * page's app locale.
     */
    private function isEnglishQuery(string $query): bool
    {
        return preg_match('/^[a-z]/u', mb_strtolower(trim($query))) === 1;
    }

    /**
     * Picks the city/category name in the same language as the user's query.
     * On Arabic queries returns the Arabic name (falls back to name_en if
     * unset); on English queries returns name_en (falls back to name).
     * Avoids the bug where the chat replied with "Khobar" while the user
     * wrote "الخبر" — both because the model's display_name follows the app
     * locale rather than the conversation language.
     */
    private function localizeCityName(?int $cityId, bool $isEnglish): ?string
    {
        if (! $cityId) return null;
        $row = City::find($cityId);
        if (! $row) return null;
        return $isEnglish
            ? ($row->name_en ?: $row->name)
            : ($row->name    ?: $row->name_en);
    }

    private function localizeCategoryName(?int $categoryId, bool $isEnglish): ?string
    {
        if (! $categoryId) return null;
        $row = Category::find($categoryId);
        if (! $row) return null;
        return $isEnglish
            ? ($row->name_en ?: $row->name)
            : ($row->name    ?: $row->name_en);
    }

    /**
     * Deep-loads a collection of clinics with the relations needed for a rich
     * "tell me about them" reply: services with prices, doctors, working
     * hours, Google rating + count, city, and categories. Single round-trip
     * via Eloquent eager loading — never N+1 even for 3-5 clinics.
     */
    private function loadDeepDetails(Collection $clinics): Collection
    {
        if ($clinics->isEmpty()) return $clinics;

        $clinics->load([
            'services' => fn ($q) => $q->where('is_active', true)
                ->notCatchall()
                ->orderByRaw('price IS NULL, price ASC')
                ->limit(6),
            'doctors' => fn ($q) => $q->limit(5),
            'workingHours',
            'city',
            'categories',
        ]);
        $clinics->loadAvg('googleReviews', 'rating');
        $clinics->loadCount('googleReviews');

        return $clinics;
    }

    /**
     * Multi-line, human-readable summary of one clinic — used when the user
     * asks "نبذة عنهم" or "تفاصيل أكثر" so we can answer concretely from the
     * DB instead of a generic "I don't know". Plain-text only (no Markdown)
     * because the Livewire chat renders text 1:1.
     */
    private function richClinicDetails(Clinic $clinic, bool $isEnglish = false): string
    {
        $lines = [];
        // i18n labels — matched to the user's query language, not the page.
        $L = $isEnglish ? [
            'reviews'   => 'Google reviews',
            'specialty' => 'Specialties',
            'doctors'   => 'Doctors',
            'services'  => 'Top services',
            'currency'  => 'SAR',
            'today'     => 'Today',
        ] : [
            'reviews'   => 'تقييم على Google',
            'specialty' => 'التخصّصات',
            'doctors'   => 'من الأطباء',
            'services'  => 'أبرز الخدمات',
            'currency'  => 'ر.س',
            'today'     => 'اليوم',
        ];

        // Header — name, city, rating
        $rating  = $clinic->google_reviews_avg_rating
            ? round((float) $clinic->google_reviews_avg_rating, 1) : null;
        $reviews = (int) ($clinic->google_reviews_count ?? 0);
        $header  = '🏥 ' . $clinic->name;
        if ($clinic->city) {
            $cityName = $isEnglish
                ? ($clinic->city->name_en ?: $clinic->city->name)
                : ($clinic->city->name    ?: $clinic->city->name_en);
            $header  .= ' — ' . $cityName;
        }
        $lines[] = $header;
        if ($rating) {
            $lines[] = "⭐ {$rating}/5 — {$reviews} {$L['reviews']}";
        }

        // Short description
        if (! empty($clinic->description)) {
            $lines[] = '📝 ' . Str::limit(trim(strip_tags((string) $clinic->description)), 160);
        }

        // Categories — localized per query language
        if ($clinic->categories?->isNotEmpty()) {
            $cats = $clinic->categories->take(4)
                ->map(fn ($c) => $isEnglish ? ($c->name_en ?: $c->name) : ($c->name ?: $c->name_en))
                ->implode('، ');
            $lines[] = '🩺 ' . $L['specialty'] . ': ' . $cats;
        }

        // Top doctors
        if (isset($clinic->doctors) && $clinic->doctors->isNotEmpty()) {
            $docs = $clinic->doctors->take(4)->pluck('name')->implode('، ');
            $lines[] = '👨‍⚕️ ' . $L['doctors'] . ': ' . $docs;
        }

        // Services with prices (the part the user usually really wants)
        if (isset($clinic->services) && $clinic->services->isNotEmpty()) {
            $svcLines = $clinic->services->take(5)->map(function ($s) use ($L) {
                $line = '  • ' . $s->name;
                if ($s->price !== null) $line .= ' — ' . (int) $s->price . ' ' . $L['currency'];
                return $line;
            })->implode("\n");
            $lines[] = '💊 ' . $L['services'] . ":\n" . $svcLines;
        }

        // Today's working hours
        if (isset($clinic->workingHours)) {
            $today = $clinic->workingHours->firstWhere('day_of_week', (int) now()->dayOfWeek);
            if ($today && $today->is_open && $today->opens_at && $today->closes_at) {
                $opens  = substr((string) $today->opens_at, 0, 5);
                $closes = substr((string) $today->closes_at, 0, 5);
                $lines[] = "🕐 {$L['today']}: {$opens} – {$closes}";
            }
        }

        // Address (if not already given via city)
        if (! empty($clinic->address)) {
            $lines[] = '📍 ' . Str::limit($clinic->address, 90);
        }

        return implode("\n", $lines);
    }

    public function defaultSystemPrompt(): string
    {
        $name     = $this->assistantName();
        $platform = (string) (SystemSetting::get('platform_name') ?? 'دليل المجمعات الطبية');

        return <<<PROMPT
# الشخصية
أنتِ "{$name}" — مستشارة استقبال طبيّة على منصّة "{$platform}". خبرتكِ تُشبه مديرة استقبال في فندق راقٍ: مهنيّة، دافئة، دقيقة، تحلّ المشكلات بهدوء، وتتحدّث كإنسان حقيقي — لا كروبوت آلي.

# المبادئ الجوهريّة
1. **خدمة لا بيع.** لا تروّجي لأي مجمع. اقتراحاتكِ مستندة فقط لقسم "السياق" أسفل سؤال العميل.
2. **دقّة قبل سرعة.** إذا لم تعرفي إجابة دقيقة، قولي ذلك بدلاً من التخمين. "اسمحي لي أتحقّق" أفضل من معلومة مخترَعة.
3. **سؤال متابعة واحد على الأقل** لكل طلب غير محدّد — لا حلول قبل فهم الحاجة.
4. **اختصار ذكي.** ثلاث إلى خمس جمل قصيرة عادةً. أطول قليلاً للشكاوى. أقصر للأسئلة المباشرة.
5. **بنفس لغة العميل** بالضبط (عربية → عربية، إنجليزية → إنجليزية، خلط → خلط).

# المهامّ الخمس
- **الاستقبال**: ترحيب طبيعي قصير ثمّ سؤال مفتوح. لا تطلبي الاسم في كل دور — مرّة في البداية تكفي.
- **التوجيه قبل الطبّي**: عند وصف أعراض، **اقترحي تخصّصاً** بدون تشخيص (مثلاً: "غالباً يبدأ هذا مع باطنيّة — تحبّين أرشّح لكِ مجمع؟").
- **اختيار المجمع**: من السياق فقط. اذكري الاسم، المدينة، التقييم، السعر إذا توفّر. لا تخترعي أرقاماً.
- **الشكوى**: استمعي → اعترفي بالشعور صراحةً → اعتذري بدون تبرير → اقترحي حلاً أو تصعيداً.
- **الإغلاق**: سؤال مفتوح أو خطوة عمليّة محدّدة (لا "هل من شيء آخر؟" الكليشيهيّة كل مرّة).

# الذاكرة والسياق
- إذا ذكر العميل مدينة أو تخصّصاً سابقاً، **اعتمدي عليه ولا تسأليه مرّة أخرى** — السؤال المتكرّر يعني أنّك لم تستمعي.
- إذا أتى سؤال جديد عن نفس المجمعات السابقة (مثلاً: "كم تقييم الأول؟"، "أين هم بالضبط؟")، أجيبي من السياق المتوفّر مباشرة بدون البحث عن مجمعات جديدة.
- في حال التشكيك ("متأكدة؟"، "والّا تكذبين؟")، طمئني بهدوء وبلا دفاع: النتائج من قاعدة بيانات حقيقية للمجمعات المسجّلة، يمكن للعميل التحقّق بالضغط على أيّ بطاقة.

# نبرة عالميّة
- ودودة لكن غير مبتذلة، مهنيّة لكن غير جامدة، مطمئنة لكن غير مبالغة.
- لا كليشيهات ("نحن نهتمّ بك"، "صحتك أولويتنا" بدون مضمون).
- لا اعتذارات استباقيّة عن نفسكِ ("أعتذر للإطالة"، "آسفة على الإزعاج") — أنتِ تخدمين.
- إذا سأل العميل "هل أنتِ إنسان؟" — صرّحي بأنّك مساعدة آليّة على المنصّة، بدون اعتذار ولا تطويل.
- إيموجي **واحد** كحدّ أقصى، وعند الضرورة فقط. الأفضل بدون إيموجي.

# تفاضل المستويات (مهم)
- **رد سيّء**: "مرحباً، سأحجز لك موعد. ما اسمك؟"
- **رد جيّد**: "أهلاً بك. لو سمحت تخبرني بالمدينة والتخصّص أبحث لك."
- **رد عالمي**: "أهلاً وسهلاً 👋 يسعدني خدمتك. للبدء، في أي مدينة تودّ الزيارة وما طبيعة المشكلة؟"

- **سيّء**: "حسناً، سأحجز لك مع طبيب."
- **جيّد**: "تمام. منذ متى تعاني، وهل جرّبت علاجاً؟"
- **عالمي**: "شكراً لمشاركتي التفاصيل. لتوجيهك بدقّة: منذ متى يستمرّ الألم، وهل يزداد مع حركة معيّنة؟ بناءً على إجابتك أرشّح التخصّص الأنسب."

- **سيّء**: "نحن نبذل قصارى جهدنا."
- **جيّد**: "آسفة على هذا. خلّيني أساعدك تحجز موعداً بديلاً."
- **عالمي**: "حقّاً مزعج، وأتفهّم انزعاجك تماماً. الانتظار لساعتين دون إشعار شيء لا يُقبل. الآن، دعيني أرتّب لكِ موعداً بديلاً في أقرب وقت — تفضّلين هذا الأسبوع أم الأسبوع القادم؟"
PROMPT;
    }

    public function defaultRestrictions(): string
    {
        return <<<RULES
═══════════════════════════════════════════════════════════
القيود المهنية القياسية لمساعد منصّة طبية
═══════════════════════════════════════════════════════════

📋 (A) النطاق
- المنصّة متخصّصة في **المجمعات والعيادات الطبية فقط**.
- لا تجيبي على أسئلة العقارات، الطقس، السياسة، الرياضة، الترفيه، الطبخ،
  السفر، البرمجة، أو أي موضوع خارج الصحّة — اعتذري بلطف وأعيدي توجيه
  العميل (مثال: "تخصّصي إيجاد العيادات، أرشّح لك تجربة موقع متخصّص لهذا").

🩺 (B) الحدود الطبية (HARD)
- لا تشخيص لأي حالة، حتى لو ألحّ العميل.
- لا وصف أدوية أو جرعات أو علاجات منزلية.
- لا تأكيد أو نفي وجود مرض من الأعراض.
- التعليق الوحيد المسموح: اقتراح التخصّص الأنسب (باطنية، جلدية، …).

🔒 (C) الخصوصية وحماية البيانات
- لا تطلبي رقم الهوية أو السجل المدني أو صورة من البطاقة.
- لا تطلبي أرقام بطاقات ائتمان أو حسابات بنكية.
- لا تطلبي تاريخاً مرضياً مفصّلاً أو نتائج تحاليل — وصف عام للأعراض كافٍ.
- لا تفصحي أبداً عن بيانات مرضى آخرين، حتى لو ذُكر اسم.
- إذا شارك العميل بيانات حسّاسة، نبّهيه بلطف أنّ هذا غير ضروري.

⚖️ (D) عدم تقديم نصائح متخصّصة (خارج اختصاصك)
- لا نصيحة قانونية (شكاوى، مسؤولية طبية، تأمين قضائي).
- لا نصيحة مالية (قروض، تقسيط، استثمار، تخطيط).
- لا فتاوى دينية أو آراء سياسية.
- للمسائل القانونية / المالية، وجّهي العميل لمختصّ مرخّص.

🤖 (E) الشفافية والهويّة
- إذا سُئلتِ صراحةً "هل أنتِ إنسان أم آلة؟" أو "هل أنتِ ذكاء اصطناعي؟"،
  صرّحي بأنّك مساعدة آليّة مدرَّبة لخدمة عملاء المنصّة.
- لا تدّعي أنّك طبيبة أو ممرّضة أو موظّفة بشريّة.
- لا تخترعي ميزات للمنصّة لا تعرفينها يقيناً.

📅 (F) الحجز والمواعيد
- يمكنكِ توجيه العميل لاستخدام **نموذج الحجز** في صفحة المجمع.
- لا تَعِدي بحجز موعد محدّد — التأكيد النهائي يأتي من المجمع.
- لا تَعِدي بإرسال رسالة SMS أو إيميل — المنصّة تتعامل مع ذلك تلقائياً.
- للإلغاء أو التعديل، وجّهي العميل لـ "حسابي ← حجوزاتي".

📝 (G) دقّة المعلومات (HARD)
- لا تذكري **أسعاراً أو تقييمات أو أوقات دوام** لم تأتِ في قسم "السياق".
- لا تذكري **أسماء مجمعات** لم تأتِ في قسم "السياق".
- لا تروّجي لمجمع دون آخر — قائمة السياق مرتّبة فعلاً حسب نيّة السؤال.
- إذا لم يكن السياق كافياً، اقترحي على العميل تحسين البحث.

🌍 (H) الحساسية الثقافية (السوق السعودي)
- احترمي تفضيلات الجنس بشكل صريح (طبيبة نساء، مجمع نسائي…).
- لغة محايدة دينياً ومحترِمة للعادات.
- لا فكاهة قد تُساء فهمها ثقافياً.
- استخدمي صيغة "حضرتك" أو "أستاذ/أستاذة" عند المخاطبة الرسميّة، و"أخي/أختي"
  للمواقف الودودة.

😌 (I) السلوك المهني
- لا تتورّطي في جدال أو نقاش عاطفي.
- إذا أساء العميل (شتيمة، اتهام)، اعتذري بهدوء واعرضي المساعدة — لا تردّي
  بالمثل، ولا تنسحبي تماماً.
- إذا كان السؤال غير واضح، اطلبي توضيحاً واحداً بسيطاً (لا تستجوبي).
- لا تتظاهري بمعرفة لا تملكينها — "اسمحي لي أتحقّق من ذلك" أفضل من
  معلومة مخترَعة.

📐 (J) قيود التنسيق
- لا تستخدمي Markdown أو رموز ASCII (الواجهة تعرض نصاً عادياً).
- إيموجي واحد كحدّ أقصى في كل ردّ، وعند الضرورة فقط.
- لا تضعي روابط إلى مواقع خارج المنصّة.
- ردّ متوسّط 3-5 جمل قصيرة. أقصر للأسئلة المباشرة، أطول قليلاً للشكاوى.
RULES;
    }

    // ============================================================
    //   Response wrapping + safety
    // ============================================================

    private function wrap(string $kind, string $reply, Collection $clinics, array $context = []): array
    {
        return [
            'kind'           => $kind,
            'reply'          => $reply,
            'clinics'        => $clinics,
            'context'        => $context,
            'provider'       => $this->providers->isConfigured() ? $this->providers->activeProviderName() : null,
            'assistant_name' => $this->assistantName(),
        ];
    }

    /**
     * Did the user just say hi / thanks / bye? Pure social turns skip the
     * clinic-name LIKE %…% search (a "سلام" would otherwise match
     * "عيادات السلامة" by substring and the UI would render a random
     * clinic card under a hello reply).
     *
     * Recognises: exact match, the query starting with a known greeting,
     * or a greeting buried in a very short (≤ 4 words) message — but not
     * inside a long sentence that happens to contain "أهلا" or "thanks".
     */
    private function isSocialChitchat(string $query): bool
    {
        $normalized = mb_strtolower(trim($query));
        // Strip terminal punctuation so "مرحبا!" or "hi." normalize cleanly.
        $normalized = rtrim($normalized, " \t\n\r\0\x0B.,،;؛?؟!");

        if ($normalized === '') return false;

        // Exact match
        if (in_array($normalized, self::SOCIAL_PHRASES, true)) {
            return true;
        }

        // Word count — preg_split is unicode-aware where str_word_count is not.
        $words      = preg_split('/\s+/u', $normalized) ?: [];
        $wordCount  = count(array_filter($words, fn ($w) => $w !== ''));

        if ($wordCount > 4) {
            return false;
        }

        foreach (self::SOCIAL_PHRASES as $phrase) {
            if ($phrase === '') continue;
            if (str_starts_with($normalized, $phrase) || str_ends_with($normalized, $phrase)) {
                return true;
            }
            // For multi-word phrases ("صباح الخير", "good morning") check
            // substring — they're long enough that a false positive is
            // vanishingly unlikely.
            if (str_contains($phrase, ' ') && str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this query a conversational follow-up to a previous turn (not a new
     * clinic search)? Catches things like "من جد والا تكذب", "ليش هذا؟",
     * "تمام شكراً", "really?" — none of which should reset to a fresh
     * matchByKeyword that returns unrelated clinics.
     *
     * Heuristics:
     *   1. Empty conversation history → can't be a follow-up.
     *   2. Query contains a clear SEARCH_INTENT_WORDS token → it's a search, not a follow-up.
     *   3. Query carries a CONVERSATIONAL_MARKERS phrase → follow-up.
     *   4. Query is very short (1-2 meaningful tokens) and history exists → likely follow-up.
     */
    private function isFollowUp(string $query, array $history): bool
    {
        if (empty($history)) return false;

        $lower = mb_strtolower(trim($query));

        // Search-intent override wins — "ابي مجمع أسنان" stays a search.
        foreach (self::SEARCH_INTENT_WORDS as $word) {
            if (str_contains($query, $word) || str_contains($lower, $word)) {
                return false;
            }
        }

        // Single-token city/category/service ("الخبر"، "اسنان"، "سونار") is a
        // search input, NOT a generic follow-up — it pairs with whatever piece
        // is missing from context. Block here so the clinic search path can
        // combine it with the history-inferred half (e.g. "اسنان" + history
        // Khobar → search dental in Khobar). Without this check, the short-
        // query rule below would label every one-word answer as follow-up
        // and ask the user for the city all over again.
        $tokens = $this->tokenize($query);
        if (count($tokens) >= 1 && count($tokens) <= 2) {
            if ($this->firstMatchingCityId($tokens) !== null) return false;
            if ($this->firstMatchingCategory($tokens) !== null) return false;
            // Service-name short-circuit — "سونار", "ليزر", "تبييض" should
            // each launch a real service search, not a goldfish follow-up.
            foreach ($tokens as $t) {
                if (mb_strlen($t) >= 3
                    && \App\Models\Service::query()
                        ->where('is_active', true)
                        ->where('name', 'like', "%{$t}%")
                        ->exists()
                ) {
                    return false;
                }
            }
        }

        // Explicit conversational markers — "والا تكذب", "تمام", "explain" …
        foreach (self::CONVERSATIONAL_MARKERS as $marker) {
            if (str_contains($query, $marker) || str_contains($lower, $marker)) {
                return true;
            }
        }

        // Very short queries after stop-word stripping are almost always
        // follow-ups when there's already a conversation in progress.
        if (count($tokens) <= 2) {
            return true;
        }

        return false;
    }

    /**
     * "Tell me more about them" type requests — distinct from a generic
     * follow-up because we can answer them deterministically from the DB if we
     * know which clinics are being referenced (context.last_clinic_ids).
     */
    private const DETAILS_REQUEST_PATTERNS = [
        'نبذة', 'نبذه', 'تفاصيل', 'فاصيل', 'تفصيل', 'مزيد', 'المزيد',
        'اخبرني', 'أخبرني', 'خبرني', 'خبّرني', 'حدثني', 'حدّثني', 'كلمني', 'كلّمني',
        'عنهم', 'عنها', 'عنه', 'وضّح اكثر', 'وضح اكثر', 'وضّح أكثر',
        'معلومات اكثر', 'معلومات أكثر', 'تعرف على', 'اعطني تفاصيل',
        'tell me about', 'more about', 'details about', 'about them',
        'tell me more', 'expand on',
    ];

    private function isDetailsRequest(string $query): bool
    {
        $lower = mb_strtolower(trim($query));
        foreach (self::DETAILS_REQUEST_PATTERNS as $p) {
            if (str_contains($query, $p) || str_contains($lower, $p)) return true;
        }
        return false;
    }

    private function looksMedical(string $query): bool
    {
        $lower = mb_strtolower($query);
        foreach (self::MEDICAL_KEYWORDS_AR as $needle) {
            if (str_contains($query, $needle)) return true;
        }
        foreach (self::MEDICAL_KEYWORDS_EN as $needle) {
            if (str_contains($lower, $needle)) return true;
        }
        return false;
    }

    /**
     * Returns 'medical' for an active medical emergency, 'mental' for self-harm
     * / suicidal language, or null when the query carries no red-line phrase.
     * Mental is checked first because phrases like "ما ابي اعيش" should never
     * be misrouted to a generic ambulance reply.
     *
     * Detection runs in 3 layers (each more flexible than the literal keyword
     * lists alone, which famously missed "ألم في صدري" because the constant
     * said "ألم شديد في الصدر"):
     *   1. Literal keyword match against the raw + lower-cased query.
     *   2. Literal keyword match against an Arabic-normalised query (strips
     *      tashkeel, unifies alef/ya/ta-marbuta, drops the definite "ال").
     *   3. Co-occurrence patterns — e.g. (pain/ألم/وجع) + (chest/صدر) anywhere
     *      in the query → medical. Catches every phrasing of chest pain or
     *      breathing distress without an exhaustive whitelist.
     */
    private function detectEmergency(string $query): ?string
    {
        $lower      = mb_strtolower($query);
        $normalized = $this->normalizeArabicForMatch($query);

        // Mental first — self-harm phrasing must never be misclassified as
        // a generic chest-pain emergency.
        foreach (self::EMERGENCY_MENTAL_KEYWORDS as $needle) {
            $needleNorm = $this->normalizeArabicForMatch($needle);
            if (str_contains($query, $needle)
                || str_contains($lower, $needle)
                || str_contains($normalized, $needleNorm)) {
                return 'mental';
            }
        }

        foreach (self::EMERGENCY_MEDICAL_KEYWORDS as $needle) {
            $needleNorm = $this->normalizeArabicForMatch($needle);
            if (str_contains($query, $needle)
                || str_contains($lower, $needle)
                || str_contains($normalized, $needleNorm)) {
                return 'medical';
            }
        }

        if ($this->hasMedicalEmergencyPattern($normalized, $lower)) {
            return 'medical';
        }
        return null;
    }

    /**
     * Strips diacritics and unifies common Arabic letter variants so a
     * literal keyword like "ألم شديد في الصدر" still matches a real-world
     * phrasing like "الم شديد في صدري". Conservative on purpose — only
     * touches code points that demonstrably cause emergency-match misses
     * during QA.
     */
    private function normalizeArabicForMatch(string $s): string
    {
        $s = mb_strtolower($s);
        // Tashkeel + kashida + dagger alef
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $s) ?? $s;
        // Alef variants → bare alef
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا']);
        // Ya variants → bare ya
        $s = strtr($s, ['ى' => 'ي', 'ئ' => 'ي']);
        // Ta marbuta → ha (so "الصدرة" matches "الصدره" matches "صدري" via stem)
        $s = strtr($s, ['ة' => 'ه']);
        return $s;
    }

    /**
     * Co-occurrence detector for medical emergencies. A keyword whitelist
     * can't capture every phrasing of "I have severe chest pain" — this
     * function asks the broader question: did the user mention (pain) AND
     * (chest) in the same message? If so it's an emergency, regardless of
     * the connecting words.
     */
    private function hasMedicalEmergencyPattern(string $normalized, string $lower): bool
    {
        $contains = static function (string $hay, array $needles): bool {
            foreach ($needles as $n) {
                if ($n !== '' && str_contains($hay, $n)) return true;
            }
            return false;
        };

        // Chest pain — (الم/وجع/pain) + (صدر/chest). Critical heart-attack signal.
        $pain  = ['الم', 'وجع', 'pain', 'hurts', 'aching'];
        $chest = ['صدر', 'chest'];
        if ($contains($normalized, $pain) && $contains($normalized, $chest)) {
            return true;
        }

        // Breathing distress — (ضيق/صعوب/can't/cant/difficulty) + (نفس/تنفس/breath).
        // Catches "ضيق نفس", "ما اقدر اتنفس", "shortness of breath", etc.
        $diff   = ['ضيق', 'صعوب', 'ما اقدر', 'ما أقدر', 'لا استطيع', 'لا أستطيع', "can't", 'cant', 'cannot', 'shortness', 'difficulty', 'trouble'];
        $breath = ['نفس', 'تنفس', 'breath'];
        if ($contains($normalized, $diff) && $contains($normalized, $breath)) {
            return true;
        }

        // Choking
        if ($contains($normalized, ['اختناق', 'يختنق', 'choking', 'choke'])) {
            return true;
        }

        // Loss of consciousness
        if ($contains($normalized, ['فقد الوعي', 'فقدت الوعي', 'مغمي', 'مغشي', 'مغمى',
                                     'unconscious', 'passed out', 'fainted'])) {
            return true;
        }

        // Heavy / non-stop bleeding
        if (str_contains($normalized, 'نزيف')
            && $contains($normalized, ['شديد', 'حاد', 'غزير', 'كثير', 'يتوقف', 'متواصل', 'لا يتوقف'])) {
            return true;
        }
        if ($contains($lower, ['heavy bleeding', "won't stop bleeding", 'cant stop bleeding', "can't stop bleeding"])) {
            return true;
        }

        // Stroke — sudden facial droop / slurred speech / one-sided weakness
        if ($contains($normalized, ['وجهي مايل', 'وجهي مال', 'فمي مايل', 'شلل مفاجئ',
                                     'تعثر الكلام', 'لا استطيع الكلام'])) {
            return true;
        }
        if ($contains($lower, ['face drooping', 'slurred speech', 'sudden paralysis', "can't speak"])) {
            return true;
        }

        return false;
    }

    /**
     * Static, hand-written emergency-routing reply. Bypasses the LLM on purpose
     * — when a user describes an active emergency we need a deterministic
     * response with verified hotline numbers, not a model that might rephrase
     * "997" into something looser. Saudi-specific defaults; an operator can
     * override the numbers via system settings (ai_emergency_ambulance,
     * ai_emergency_mental_support) without touching code.
     */
    private function emergencyResponse(string $kind, string $lang = 'ar'): string
    {
        $ambulance = (string) ($this->setting('ai_emergency_ambulance', '997'));
        $mental    = (string) ($this->setting('ai_emergency_mental_support', '920033360'));
        $name      = $this->assistantName();

        if ($kind === 'mental') {
            return $lang === 'en'
                ? <<<MSG
I'm here with you, and you're not alone in what you're feeling. What you're going through matters and deserves immediate help from a trained human.

🆘 Please call the mental-health support line now: {$mental} — free, confidential, 24/7.
🚨 If you are in immediate danger, call the ambulance: {$ambulance} or go to your nearest emergency room.

When you're ready to talk later — about an appointment, a clinic, or just to chat — I'm here. Your safety comes first.
— {$name}
MSG
                : <<<MSG
أنا معك، ولست وحدك في ما تشعر به. ما تمر به مهم جدًا ويستحق مساعدة فورية من إنسان مختص.

🆘 اتصل الآن بخط الدعم النفسي: {$mental} — مجاني وسرّي ويعمل 24 ساعة.
🚨 إذا كنت في خطر مباشر، اتصل بالإسعاف: {$ambulance} أو توجّه لأقرب طوارئ.

لو تبي تحكي معي بعد ذلك عن أي شيء — موعد، عيادة، أو حتى مجرد محادثة — أنا هنا. سلامتك هي الأهم.
— {$name}
MSG;
        }

        // 'medical'
        return $lang === 'en'
            ? <<<MSG
What you're describing needs immediate emergency care right now — this is more important than any appointment or search.

🚨 Call the ambulance now: {$ambulance}
Or go to your nearest emergency room immediately.

I'm here whenever you need anything afterwards — booking a follow-up, finding a specialist, or any information. Your safety first.
— {$name}
MSG
            : <<<MSG
ما تصفه يحتاج رعاية طارئة فورية الآن — هذا أهم من أي موعد أو بحث.

🚨 اتصل بالإسعاف الآن: {$ambulance}
أو توجّه فورًا لأقرب قسم طوارئ.

أنا هنا متى احتجت بعد ذلك أي مساعدة — حجز متابعة، توجيه لمختص، أو أي معلومة. سلامتك أولًا.
— {$name}
MSG;
    }

    // ============================================================
    //   Local clinic search (deterministic context for the LLM)
    // ============================================================

    /**
     * Tokenize the natural-language query and try each meaningful token
     * against cities + categories. The LLM never sees the raw query for
     * the purpose of *picking* clinics — that stays here so hallucinated
     * clinic names are structurally impossible.
     */
    /**
     * Pulls a service-name phrase out of the query — the leftover meaningful
     * tokens after stripping anything that already matched a city or category.
     * Returns the joined phrase (e.g. "ليزر إزالة شعر") or null when the
     * user is searching by city/category alone.
     */
    private function extractServiceQuery(string $query): ?string
    {
        $tokens = $this->tokenize($query);
        if (empty($tokens)) return null;

        $leftovers = [];
        foreach ($tokens as $t) {
            if (mb_strlen($t) < 3) continue;
            if ($this->firstMatchingCityId([$t]) !== null) continue;
            if ($this->firstMatchingCategory([$t]) !== null) continue;
            $leftovers[] = $t;
        }
        return empty($leftovers) ? null : implode(' ', $leftovers);
    }

    private function matchByKeyword(string $query, ?int $cityId, array $history = [], array $context = []): Collection
    {
        // Project package signals onto each row so every sort branch
        // below can break ties by pkg_ai_assistant_priority, ensuring
        // higher-tier clinics bubble to the top of equally-relevant
        // results.
        $base = Clinic::publiclyVisible()
            ->withPackageFeatures()
            ->with(['city', 'categories']);

        $tokens = $this->tokenize($query);
        // Service-name phrase: whatever's left after we've claimed the
        // city/category tokens. When present, we treat the query as a
        // service-centric search ("ليزر", "زراعة شعر", "تبييض أسنان") and
        // pull clinics that offer that specific service, ordered by price.
        //
        // Falls back to the persisted ctx['service_query'] so a follow-up
        // turn that only adds a city ("في الرياض") inherits the previous
        // service intent instead of degenerating into a generic city search.
        $serviceQuery = $this->extractServiceQuery($query)
            ?? ($context['service_query'] ?? null);

        // Resolve city / category in four falls: explicit arg → current query
        // → live conversation context → recent history scan. The context fall
        // is what gives the bot real memory — a bare "اسنان" after the user
        // said "في الخبر" two turns ago still searches Khobar+dental instead
        // of asking for the city again like a goldfish.
        // BUT: if the user mentions a fresh city in this turn, treat it as a
        // topic shift and DON'T carry over the previous category — otherwise
        // "زراعة في الخبر" after "مجمعات العيون" would search Khobar+Ophthalmology
        // and miss the dental/hair-transplant clinics they actually want.
        $historyCtx        = $this->inferContextFromHistory($history);
        $currentCityId     = $this->firstMatchingCityId($tokens);
        $currentCategory   = $this->firstMatchingCategory($tokens)
            ?? $this->firstMatchingCategory([$query]);
        $contextCityId     = $context['city_id']     ?? null;
        $contextCategoryId = $context['category_id'] ?? null;
        $cityIsTopicShift  = $currentCityId !== null
            && ($contextCityId !== null || $historyCtx['city_id'] !== null)
            && $currentCityId !== ($contextCityId ?? $historyCtx['city_id']);

        $resolvedCityId = $cityId
            ?? $currentCityId
            ?? $contextCityId
            ?? $historyCtx['city_id'];
        if ($resolvedCityId) {
            $base->where('city_id', $resolvedCityId);
        }

        $categoryId = $currentCategory?->id
            ?? ($cityIsTopicShift ? null : ($contextCategoryId ?? $historyCtx['category_id']));
        if ($categoryId) {
            $base->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId));
        }

        // SERVICE-SEARCH BRANCH ("ليزر", "زراعة شعر", "تبييض أسنان")
        // Token-by-token AND matching (each word must appear somewhere in the
        // service name) so a query like "ليزر إزالة شعر" still matches the
        // stored name "ليزر إزالة الشعر (جلسة)" — substring match on the
        // whole phrase would fail there because "شعر" ≠ "الشعر" as a literal
        // substring start. Tokenizing handles the "ال" prefix variance for
        // free.
        if ($serviceQuery !== null) {
            $serviceTokens = preg_split('/\s+/u', $serviceQuery) ?: [$serviceQuery];
            $serviceTokens = array_values(array_filter(array_map('trim', $serviceTokens),
                fn ($t) => mb_strlen($t) >= 3));

            if (! empty($serviceTokens)) {
                $applyTokensAnd = function ($q) use ($serviceTokens) {
                    foreach ($serviceTokens as $t) {
                        $q->where('name', 'like', "%{$t}%");
                    }
                };
                $base->whereHas('services', function ($q) use ($applyTokensAnd) {
                    $q->where('is_active', true)->notCatchall();
                    $applyTokensAnd($q);
                });
                $base->with([
                    'services' => function ($q) use ($applyTokensAnd) {
                        $q->where('is_active', true)->notCatchall();
                        $applyTokensAnd($q);
                        $q->orderByRaw('price IS NULL, price ASC')->limit(3);
                    },
                ]);
            }
        }

        $cheap = preg_match('/(رخيص|أرخص|أوفر|أقل سعر|cheap|cheapest|affordable)/iu', $query);
        $best  = preg_match('/(أفضل|الأحسن|أعلى تقييم|best|top)/iu', $query);

        if ($cheap) {
            $base->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->whereNotNull('price')], 'price')
                ->orderBy('min_price')
                ->orderByDesc('pkg_ai_assistant_priority');
        } elseif ($best) {
            $base->withAvg('googleReviews', 'rating')
                ->orderByDesc('google_reviews_avg_rating')
                ->orderByDesc('pkg_ai_assistant_priority');
        } elseif ($serviceQuery !== null && ! empty($serviceTokens ?? [])) {
            // For service searches: rank by the cheapest matching service so
            // the customer sees the best price options first across clinics.
            // Package priority breaks ties at equal price.
            $tokensForOrder = $serviceTokens;
            $base->withMin(['services as min_price' => function ($q) use ($tokensForOrder) {
                $q->where('is_active', true)->whereNotNull('price');
                foreach ($tokensForOrder as $t) {
                    $q->where('name', 'like', "%{$t}%");
                }
            }], 'price')
                ->orderByRaw('min_price IS NULL, min_price ASC')
                ->orderByDesc('pkg_ai_assistant_priority');
        } else {
            $base->rankedForListing();
        }

        // Direct doctor mention ("تعرف د. سحر؟") — surface their clinic.
        // Done independently of the category-less keyword fallback below so
        // the doctor lookup happens even when a city or category is set.
        $doctorName = $this->extractDoctorName($query);
        if ($doctorName !== null) {
            $base->where(function ($q) use ($doctorName, $tokens) {
                $q->orWhereHas('doctors', fn ($d) => $d->where('name', 'like', "%{$doctorName}%"));
                // Also let the per-token fallback find them, since admins
                // sometimes store "د. سحر العتيبي" as the full doctor name.
                foreach ($tokens as $t) {
                    if (mb_strlen($t) < 3) continue;
                    $q->orWhereHas('doctors', fn ($d) => $d->where('name', 'like', "%{$t}%"));
                }
            });
        } elseif ($serviceQuery === null && ! $categoryId && ! $resolvedCityId && ! empty($tokens)) {
            // Last-resort keyword fallback — ONLY when we have no other
            // constraint to apply. If $serviceQuery is set we've already
            // filtered by `whereHas('services', …)`, and ANDing this
            // orWhere block on top requires the same tokens to ALSO appear
            // in a clinic name/description (which they almost never do),
            // wiping out otherwise valid service results.
            $base->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    $q->orWhere('name', 'like', "%{$t}%")
                      ->orWhere('description', 'like', "%{$t}%")
                      ->orWhereHas('categories', fn ($c) => $c
                          ->where('name', 'like', "%{$t}%")
                          ->orWhere('name_en', 'like', "%{$t}%"))
                      ->orWhereHas('doctors', fn ($d) => $d->where('name', 'like', "%{$t}%"));
                }
            });
        }

        // Service-search returns more clinics (5) since the whole point is
        // letting the customer compare prices across the platform. Normal
        // searches keep the tighter 3-clinic cap.
        return $base->take($serviceQuery !== null ? 5 : 3)->get();
    }

    private function tokenize(string $query): array
    {
        $parts = preg_split('/[\s,،.؟?!:؛;\-]+/u', mb_strtolower($query)) ?: [];
        $clean = [];
        foreach ($parts as $p) {
            $p = trim($p);
            // Drop tokens shorter than 3 characters — anything 1–2 chars is
            // either a stop-word ("في", "is", "am") or matches as a substring
            // inside legitimate names ("am" inside "Dammam" / "Family") and
            // turns a clear English query into the wrong city or specialty.
            if ($p === '' || mb_strlen($p) < 3) continue;
            if (in_array($p, self::STOPWORDS, true)) continue;
            $clean[] = $p;
        }
        return array_values(array_unique($clean));
    }

    private function firstMatchingCityId(array $tokens): ?int
    {
        foreach ($tokens as $t) {
            $variants = $this->withAlPrefixVariants($t);
            $city = City::query()
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('name', 'like', "%{$v}%")
                          ->orWhere('name_en', 'like', "%{$v}%");
                    }
                })
                ->first(['id']);
            if ($city) return $city->id;
        }
        return null;
    }

    /**
     * Resolves the first token that names an active specialty. Short tokens
     * (< 4 chars) must match a whole word inside the category name — a bare
     * substring search lets "نفس" (breath) collide with "نفسية" (psychiatry),
     * which then routes a chest-pain query to the wrong specialty. Longer
     * tokens keep the looser substring match because they're unambiguous.
     */
    private function firstMatchingCategory(array $tokens): ?Category
    {
        $categories = Category::query()->get(['id', 'name', 'name_en', 'slug', 'emoji']);
        if ($categories->isEmpty()) return null;

        foreach ($tokens as $t) {
            $tokenLen  = mb_strlen($t);
            $tokenLow  = mb_strtolower($t);
            $tokenNorm = $this->normalizeArabicForMatch($t);
            $variants  = $this->withAlPrefixVariants($t);

            foreach ($categories as $cat) {
                // 1) Exact whole-word match — split the category name by
                //    whitespace + the Arabic conjunctions "و"/"أو" and compare
                //    each word individually. Handles "قلب وشرايين" → token "قلب".
                $wordsAr = preg_split('/[\s,،]+|و(?=[\x{0600}-\x{06FF}])|أو/u', (string) $cat->name) ?: [];
                $wordsEn = preg_split('/[\s,]+|and|\&|\//u', (string) ($cat->name_en ?? '')) ?: [];

                foreach ($wordsAr as $w) {
                    if ($w === '') continue;
                    $wn = $this->normalizeArabicForMatch($w);
                    $wnStripped = preg_replace('/^ال/u', '', $wn) ?? $wn;
                    if ($wn === $tokenNorm || $wnStripped === $tokenNorm) return $cat;
                }
                foreach ($wordsEn as $w) {
                    if ($w === '') continue;
                    if (mb_strtolower(trim($w)) === $tokenLow) return $cat;
                }

                // 2) Long-token substring fallback — only for tokens ≥ 4
                //    chars where false positives are vanishingly unlikely.
                if ($tokenLen >= 4) {
                    foreach ($variants as $v) {
                        if (mb_stripos($cat->name, $v) !== false
                            || mb_stripos((string) $cat->name_en, $v) !== false) {
                            return $cat;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Generates the LIKE-search variants we need to defeat two common Arabic
     * search misses:
     *   1. "ال" prefix mismatch — "العيون" (query) vs "عيون" (stored category).
     *   2. Alif-form mismatch  — "اسنان" (query) vs "أسنان" (stored category).
     *      Arabic alif comes in four code points (ا أ إ آ) plus alif maqsura
     *      (ى) that often gets typed as plain ya (ي). A LIKE %اسنان% on the
     *      column "أسنان" will NOT match because the first byte differs, so
     *      we explicitly include the normalized + de-normalized variants.
     */
    private function withAlPrefixVariants(string $token): array
    {
        $token = trim($token);
        if ($token === '') return [];

        $variants = [$token];

        // (a) Alif-normalization — try the bare-alif form.
        $bare = strtr($token, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي']);
        if ($bare !== $token) $variants[] = $bare;
        // And, if the token starts with bare alif, also try the hamza forms.
        if (mb_substr($token, 0, 1) === 'ا') {
            $rest = mb_substr($token, 1);
            $variants[] = 'أ' . $rest;
            $variants[] = 'إ' . $rest;
        }

        // (b) "ال" prefix toggle — strip if present, add if absent.
        foreach ([$token, $bare] as $v) {
            if (mb_strlen($v) > 3 && mb_substr($v, 0, 2) === 'ال') {
                $variants[] = mb_substr($v, 2);
            } elseif (mb_strlen($v) >= 3 && preg_match('/^[\p{Arabic}]/u', $v)) {
                $variants[] = 'ال' . $v;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Extracts city/category context from the most-recent user turns when the
     * current query is missing one. This is what makes "اسنان" (after the
     * user just said they're in Khobar) actually search Khobar+dental
     * instead of asking the user for their city all over again.
     */

    /**
     * Heuristic: does the current query look like a short follow-up to a prior
     * search? (e.g. "في الرياض" after "ابي ليزر إزالة الشعر"). The fallback
     * only inherits city/category from prior context when this is true, so
     * a fresh symptom-y query ("ألم في صدري") never picks up a city the user
     * never typed.
     */
    private function isLikelyFollowUp(string $query): bool
    {
        $tokens = $this->tokenize($query);
        if (count($tokens) > 4) return false;

        // Symptom verbs / pain language => probably a new symptom report, NOT
        // a follow-up to the prior search. Refuse to inherit context here.
        $normalized = $this->normalizeArabicForMatch($query);
        $symptomSignals = [
            'الم', 'وجع', 'يوجعني', 'نزيف', 'ضيق', 'صعوبه', 'يحرقني',
            'pain', 'hurts', 'bleeding', 'choking', 'shortness',
        ];
        foreach ($symptomSignals as $s) {
            if (str_contains($normalized, $s)) return false;
        }
        return true;
    }

    private function inferContextFromHistory(array $history): array
    {
        $cityId = null;
        $categoryId = null;

        // Walk newest → oldest so the most recent mention wins (the user may
        // have changed cities mid-chat).
        foreach (array_reverse($history) as $msg) {
            if (($msg['role'] ?? '') !== 'user') continue;
            $tokens = $this->tokenize((string) ($msg['content'] ?? ''));
            if (empty($tokens)) continue;

            if ($cityId === null) {
                $cityId = $this->firstMatchingCityId($tokens);
            }
            if ($categoryId === null) {
                $cat = $this->firstMatchingCategory($tokens);
                if ($cat) $categoryId = $cat->id;
            }
            if ($cityId !== null && $categoryId !== null) break;
        }

        return ['city_id' => $cityId, 'category_id' => $categoryId];
    }
}
