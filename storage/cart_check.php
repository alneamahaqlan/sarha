use App\Models\CartItem;
use App\Models\CartContact;
echo "abandoned=".CartItem::abandoned()->count();
echo " booked=".CartItem::booked()->count();
echo " contacts=".CartContact::count();
echo " acq_cart=".(in_array('cart', App\Models\Booking::ACQUISITION_SOURCES)?'yes':'no');
echo " evt=".App\Enums\NotificationEvent::CART_REMINDER_DUE->title(['count'=>3]);
echo "\n";
