<?php

return [
    // Returned in the 422 response when the clinic admin tries to disable
    // a protected section (hero / contact_info / floating_ctas).
    'cannot_hide_protected' => 'لا يمكن إخفاء هذا القسم — هو جزء أساسي من صفحة المجمع. يمكن إعادة ترتيبه فقط.',
];
