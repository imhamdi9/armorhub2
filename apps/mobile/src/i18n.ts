import { getLocales } from "expo-localization";
import { I18nManager } from "react-native";

/** The app speaks the phone's language: Arabic (right to left) or English. */
export const isArabic = () => (getLocales()[0]?.languageCode ?? "en") === "ar";

// The phone's own setting decides the direction; the OS applies it at launch.
I18nManager.allowRTL(true);

const AR: Record<string, string> = {
  "Stores": "المتاجر", "Orders": "طلباتي", "Account": "حسابي", "Search": "بحث", "No products found.": "لا توجد منتجات.",
  "Sign in": "تسجيل الدخول", "Sign out": "تسجيل الخروج", "Email": "البريد الإلكتروني", "Password": "كلمة المرور",
  "Create an account": "إنشاء حساب", "Forgot your password?": "نسيت كلمة المرور؟", "Or": "أو",
  "Continue with Google": "المتابعة باستخدام جوجل", "Continue with Apple": "المتابعة باستخدام آبل",
  "Sign in to see your orders": "سجّل الدخول لترى طلباتك", "No orders yet.": "لا توجد طلبات بعد.",
  "Buy": "اشترِ", "Request a quote": "اطلب عرض سعر", "From": "من", "Description": "الوصف", "Highlights": "المميزات",
  "Specifications": "المواصفات", "Questions": "أسئلة", "Choose an option": "اختر خيارًا", "Warranty": "الضمان", "year warranty": "سنة ضمان",
  "A platform fee of {n}% is added at payment.": "تُضاف رسوم منصة {n}% عند الدفع.",
  "Delivery address": "عنوان التوصيل", "Full name": "الاسم الكامل", "Phone": "الجوال", "Address": "العنوان", "City": "المدينة",
  "Invoice": "الفاتورة", "Platform fee": "رسوم المنصة", "Total to pay": "الإجمالي المطلوب", "Place order": "تأكيد الطلب", "Pay now": "ادفع الآن",
  "Order": "الطلب", "Status": "الحالة", "Loading...": "جارٍ التحميل...", "Try again": "حاول مرة أخرى",
  "You are offline. Check your connection.": "لا يوجد اتصال. تحقق من الإنترنت.", "Something went wrong.": "حدث خطأ ما.",
  "Delete my account": "حذف حسابي", "Type DELETE to confirm": "اكتب DELETE للتأكيد", "Cancel": "إلغاء", "Delete": "حذف",
  "This removes your personal details for good. Your past orders stay as records without your name.": "يحذف هذا بياناتك الشخصية نهائيًا. تبقى طلباتك السابقة سجلات بلا اسمك.",
  "Your order is placed.": "تم تسجيل طلبك.", "Payment opens in your browser.": "يفتح الدفع في المتصفح.",
  "Notifications": "الإشعارات", "Allow notifications to hear when your order moves.": "فعّل الإشعارات لتعرف عند تقدّم طلبك.",
  "Track an order": "تتبّع طلبًا", "Shipped": "تم الشحن", "Paid": "مدفوع", "Ordered": "تم الطلب", "Delivered": "تم التسليم",
  "Waiting for payment": "بانتظار الدفع", "Completed": "مكتمل", "Cancelled": "ملغى", "In progress": "قيد التنفيذ",
  "Search products and stores": "ابحث عن منتج أو متجر",
  "Save {n}%": "وفّر {n}٪",
  "Free delivery": "توصيل مجاني",
  "Installers nearby": "مُركّبون بالقرب منك",
  "Protect your ride this season": "احمِ سيارتك هذا الموسم",
  "Verified installers near you": "مُركّبون موثّقون بالقرب منك",
  "Shop now": "تسوّق الآن",
  "Shop by category": "تسوّق حسب القسم",
  "Deals for you": "عروض لك",
  "Top rated stores": "أعلى المتاجر تقييمًا",
  "See all": "عرض الكل",
  "All": "الكل",
  "Home": "الرئيسية",
  "results": "نتيجة",
  "Sort": "الترتيب",
  "Featured": "المميّز",
  "Price: low to high": "السعر: من الأقل",
  "Price: high to low": "السعر: من الأعلى",
  "Name": "الاسم",
  "Filter": "تصفية",
  "No results.": "لا توجد نتائج.",
  "Buy now": "اشترِ الآن",
  "Total": "الإجمالي",
  "In stock": "متوفر",
  "Out of stock": "غير متوفر",
  "Includes VAT": "شامل الضريبة",
  "Secure payment": "دفع آمن",
  "Cancel before you pay": "إلغاء قبل الدفع",
  "ratings": "تقييم",
  "Checkout": "إتمام الطلب",
  "Review": "المراجعة",
  "Pay": "الدفع",
  "Your item": "منتجك",
  "Order summary": "ملخص الطلب",
  "Items": "المنتجات",
  "Delivery": "التوصيل",
  "Delivering to {name}": "التوصيل إلى {name}",
  "Your orders": "طلباتي",
  "Search orders": "ابحث في الطلبات",
  "Placed": "تاريخ الطلب",
  "Track order": "تتبّع الطلب",
  "View order": "عرض الطلب",
  "Buy again": "اشترِ مجددًا",
  "Back": "رجوع",
  "Card payment opens in the secure browser": "يفتح الدفع بالبطاقة في المتصفح الآمن",
  "Stores near you": "المتاجر",
  "Sold by": "البائع",
  "Sign in to ArmorHub": "سجّل الدخول إلى ArmorHub",
  "Welcome back": "أهلًا بعودتك",
  "Finding your market...": "نبحث عن سوقك...",
  "We use your location to show the stores and prices of your country.": "نستخدم موقعك لنعرض متاجر وأسعار بلدك.",
  "Choose your market": "اختر سوقك",
  "Use my location": "استخدم موقعي",
  "Market": "السوق",
  "Location is off. Choose your market, or allow location in Settings.": "الموقع متوقف. اختر سوقك أو فعّل الموقع من الإعدادات.",
  "ArmorHub is not in your country yet. Choose a market to browse.": "ArmorHub غير متوفر في بلدك بعد. اختر سوقًا للتصفح.",
  "We could not find your location. Choose your market.": "تعذّر تحديد موقعك. اختر سوقك.",
  "Popular products": "الأكثر رواجًا",
  "Free": "مجاني", "Not signed in": "غير مسجّل", "Open the website": "فتح الموقع",
  "Only {n} left": "بقي {n} فقط",
  "Garage": "الجراج", "Sign in to see your garage": "سجّل الدخول لترى الجراج", "Add your car to see only the parts that fit it.": "أضف سيارتك لترى القطع المناسبة لها فقط.",
  "Default": "الافتراضية", "Make": "الشركة", "Model": "الموديل", "Year": "السنة", "Save vehicle": "حفظ المركبة", "Add a vehicle": "إضافة مركبة",
  "Price requests": "طلبات الأسعار", "Sign in to see your price requests": "سجّل الدخول لترى طلبات الأسعار",
  "No price requests yet. Open a product sold by price request and tap Request a price.": "لا طلبات أسعار بعد. افتح منتجاً يُباع بطلب سعر واضغط اطلب سعراً.",
  "No quotes yet. Sellers have been asked.": "لا عروض بعد. تم سؤال البائعين.", "days": "يوم", "days warranty": "يوم ضمان", "Accept": "قبول", "Expired": "منتهٍ",
  "Request a price": "اطلب سعراً", "Save a delivery address first (Account > Addresses).": "احفظ عنوان توصيل أولاً (الحساب > العناوين).",
  "Addresses": "العناوين", "Sign in to see your addresses": "سجّل الدخول لترى العناوين", "No saved addresses yet.": "لا عناوين محفوظة بعد.", "Make default": "اجعله الافتراضي",
  "Label, e.g. Home": "تسمية، مثل المنزل", "Save address": "حفظ العنوان", "Add an address": "إضافة عنوان", "Save": "حفظ", "Saved": "تم الحفظ",
  "Cart": "السلة", "Sign in to use your cart": "سجّل الدخول لتستخدم السلة", "Your cart is empty.": "سلتك فارغة.", "Fewer": "أقل", "More": "أكثر", "Remove": "حذف",
  "Code": "الكود", "Discount code": "كود الخصم", "Apply": "تطبيق", "Discount": "الخصم", "Usually delivered in about {n} days.": "يصل عادةً خلال نحو {n} أيام.",
  "Favourites": "المفضلة", "Sign in to see your favourites": "سجّل الدخول لترى المفضلة", "Nothing saved yet. Tap the heart on a product to keep it here.": "لا شيء محفوظ بعد. اضغط القلب على أي منتج ليظهر هنا.",
  "Saved to favourites": "محفوظ في المفضلة", "Save to favourites": "احفظ في المفضلة", "Add to cart": "أضف إلى السلة", "Added to your cart.": "أُضيف إلى سلتك.",
  "Your refund is being sent back to you.": "جارٍ إرجاع مبلغك إليك.", "Your refund was sent.": "تم إرسال مبلغك.", "We received your return request. We will answer soon.": "استلمنا طلب الإرجاع. سنرد قريباً.",
  "Why do you want to cancel or return it?": "لماذا تريد الإلغاء أو الإرجاع؟", "Send request": "إرسال الطلب", "Cancel or return this order": "إلغاء الطلب أو إرجاعه",
  "Express delivery": "توصيل سريع", "Standard delivery": "توصيل عادي", "How fast, and when?": "بأي سرعة ومتى؟", "about {n} days": "نحو {n} أيام", "Preferred delivery day (optional)": "يوم التسليم المفضل (اختياري)", "Gift note (optional)": "رسالة إهداء (اختياري)", "Use {n} points": "استخدم {n} نقطة", "Takes {amount} off. You have {balance} points.": "تخصم {amount}. رصيدك {balance} نقطة.", "Preferred day": "اليوم المفضل", "Give this code to the courier when you receive the parcel. Do not share it before.": "أعطِ هذا الرمز للمندوب عند استلام الطرد فقط، ولا تشاركه قبل ذلك.", "The delivery attempt failed": "تعذّرت محاولة التسليم", "New delivery day": "يوم التسليم الجديد", "Reschedule delivery": "إعادة جدولة التسليم", "Delivery is planned for": "موعد التسليم المحدد", "Out for delivery": "خرج للتسليم", "In transit": "في الطريق", "Delivery failed": "تعذّر التسليم", "Returned": "أُعيد", "Your return was approved. Hand the parcel over to the courier.": "تمت الموافقة على إرجاعك. سلّم الطرد للمندوب.", "Give this code to whoever collects the parcel. Do not share it before.": "أعطِ هذا الرمز لمن يستلم الطرد منك فقط، ولا تشاركه قبل ذلك.", "Collection day": "يوم الاستلام", "Choose a collection day": "اختر يوم الاستلام", "Confirm day": "تأكيد اليوم", "We collected your parcel and are inspecting it. Your refund follows once it passes.": "استلمنا طردك ونفحصه الآن. يصلك المبلغ بعد اجتياز الفحص.", "Help": "المساعدة", "Preferences": "التفضيلات", "Invite friends": "ادعُ أصدقاءك", "Warranties": "الضمانات", "Ask for help": "اطلب مساعدة", "Subject": "الموضوع", "Tell us what happened": "أخبرنا بما حدث", "No help requests yet.": "لا طلبات مساعدة بعد.", "Waiting for an answer": "بانتظار الإجابة", "Answered": "تمت الإجابة", "Closed": "مغلق", "Close": "إغلاق", "How we tell you": "كيف نخبرك", "By e-mail": "بالبريد الإلكتروني", "On my phone (notifications)": "على هاتفي (إشعارات)", "The notices inside the app are always kept.": "الإشعارات داخل التطبيق تُحفظ دائماً.", "Share your link. When a friend receives their first order, you both get {n} points.": "شارك رابطك. عندما يستلم صديقك أول طلب له تحصلان معاً على {n} نقطة.", "Share my invite": "شارك دعوتي", "{n} invited": "{n} مدعو", "{n} rewarded": "{n} حصلوا على مكافأة", "Your points": "نقاطك", "No warranties yet.": "لا ضمانات بعد.", "Active": "ساري", "Until": "حتى", "What went wrong? Describe the problem.": "ما المشكلة؟ صِف ما حدث.", "Make a warranty claim": "تقديم مطالبة ضمان", "Claim sent": "تم إرسال المطالبة", "Claim approved": "تمت الموافقة على المطالبة", "Claim declined": "تم رفض المطالبة", "Claim resolved": "تم حل المطالبة", "Repair": "إصلاح", "Replacement": "استبدال", "Refund": "استرداد",
  "Messages about this order": "رسائل حول هذا الطلب", "Write a message": "اكتب رسالة", "Send": "إرسال",
};

/** Translates a phrase. `{n}`-style holes are filled from `vars`. */
export function t(text: string, vars?: Record<string, string | number>): string {
  const base = isArabic() ? (AR[text] ?? text) : text;
  return vars ? base.replace(/\{(\w+)\}/g, (_, k) => String(vars[k] ?? "")) : base;
}

export function money(value: number | string, currency: string): string {
  const n = Number(value);
  try {
    return new Intl.NumberFormat(isArabic() ? "ar" : "en", { style: "currency", currency, maximumFractionDigits: 2 }).format(n);
  } catch {
    return `${n.toFixed(2)} ${currency}`;
  }
}
