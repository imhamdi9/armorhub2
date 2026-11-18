export type PolicyKind = "privacy" | "terms" | "returns" | "shipping" | "cookies";

type Text = { en: string; ar: string };
export interface Policy {
  title: Text;
  summary: Text;
  sections: { heading: Text; body: Text[] }[];
}

export const POLICY_KINDS: PolicyKind[] = ["privacy", "terms", "returns", "shipping", "cookies"];
export const POLICIES_UPDATED = "2026-11-18";

/**
 * What each policy says is what the platform does today (return window, delivery codes, three delivery attempts, points, and so on), written in both
 * languages. The operator of a market may add local terms, and a lawyer should review these before launch.
 */
export const POLICIES: Record<PolicyKind, Policy> = {
  privacy: {
    title: { en: "Privacy policy", ar: "سياسة الخصوصية" },
    summary: { en: "What we keep about you, why, who sees it, and how you stay in control.", ar: "ما نحتفظ به عنك، ولماذا، ومن يراه، وكيف تبقى متحكماً فيه." },
    sections: [
      { heading: { en: "What we collect", ar: "ما نجمعه" }, body: [
        { en: "Your account details (name, e-mail, phone, city), the addresses and cars you save, your orders and delivery details, the reviews, questions and warranty claims you write, and your language and notification choices.", ar: "بيانات حسابك (الاسم والبريد والهاتف والمدينة)، والعناوين والسيارات التي تحفظها، وطلباتك وتفاصيل التسليم، والتقييمات والأسئلة ومطالبات الضمان التي تكتبها، واختيارات اللغة والإشعارات." },
        { en: "If you allow it, your approximate location, only to find centres near you.", ar: "وإن سمحت بذلك، موقعك التقريبي، وذلك فقط للعثور على مراكز قريبة منك." },
      ] },
      { heading: { en: "Why we use it", ar: "لماذا نستخدمها" }, body: [
        { en: "To process your orders and deliveries, take payment through our payment provider, send you updates about what you bought, protect the platform from fraud and abuse, and answer your requests.", ar: "لمعالجة طلباتك وتسليمها، وتحصيل الدفع عبر مزود الدفع، وإرسال تحديثات عمّا اشتريته، وحماية المنصة من الاحتيال وإساءة الاستخدام، والرد على طلباتك." },
      ] },
      { heading: { en: "Who sees it", ar: "من يراها" }, body: [
        { en: "The seller or service centre sees what they need to fulfil your order: your name, delivery address and phone number. Couriers get your address and ask for the delivery code you give them at the door.", ar: "يرى البائع أو المركز ما يحتاجه لتنفيذ طلبك: اسمك وعنوان التسليم ورقم هاتفك. ويحصل المندوب على عنوانك ويطلب منك رمز التسليم عند الباب." },
        { en: "Payments are handled by our payment provider; we never store full card numbers. We do not sell your personal data.", ar: "تتولى معالجة المدفوعات جهة الدفع لدينا، ولا نخزن أرقام البطاقات كاملة. ولا نبيع بياناتك الشخصية." },
      ] },
      { heading: { en: "Your rights", ar: "حقوقك" }, body: [
        { en: "You can download everything we hold about you from Account, correct it there, change your notification choices, or delete your account.", ar: "يمكنك تنزيل كل ما نحتفظ به عنك من صفحة «الحساب»، وتصحيحه، وتغيير اختيارات الإشعارات، أو حذف حسابك." },
        { en: "When you delete your account your name, e-mail, phone and addresses are removed. Orders stay as financial records, without your name.", ar: "عند حذف حسابك تُزال بياناتك: الاسم والبريد والهاتف والعناوين. وتبقى الطلبات كسجلات مالية بلا اسمك." },
      ] },
      { heading: { en: "How long we keep it", ar: "مدة الاحتفاظ" }, body: [
        { en: "Orders and invoices are kept as long as accounting and tax rules require. Other data is kept while your account is open.", ar: "نحتفظ بالطلبات والفواتير للمدة التي تتطلبها قواعد المحاسبة والضرائب. وتبقى باقي البيانات ما دام حسابك مفتوحاً." },
      ] },
    ],
  },
  terms: {
    title: { en: "Terms of use", ar: "شروط الاستخدام" },
    summary: { en: "The ground rules for buying and selling on the platform.", ar: "القواعد الأساسية للشراء والبيع على المنصة." },
    sections: [
      { heading: { en: "What the platform is", ar: "ما هي المنصة" }, body: [
        { en: "ArmorHub connects customers with independent sellers and service partners. Sellers are responsible for their products and listings. We provide secure payment, delivery tracking and help when something goes wrong.", ar: "تربط ArmorHub العملاء ببائعين ومزودي خدمة مستقلين. البائعون مسؤولون عن منتجاتهم وإعلاناتهم. ونوفر الدفع الآمن وتتبع التسليم والمساعدة عند حدوث مشكلة." },
      ] },
      { heading: { en: "Orders and payment", ar: "الطلبات والدفع" }, body: [
        { en: "The price, delivery fee, platform fee and taxes are shown before you pay. An order is confirmed once it is paid, by card or wallet on the secure payment page, or in cash on delivery where it is offered.", ar: "يظهر السعر ورسوم التوصيل ورسوم المنصة والضرائب قبل الدفع. يتأكد الطلب عند الدفع بالبطاقة أو المحفظة في صفحة الدفع الآمنة، أو نقداً عند الاستلام حيث يتوفر ذلك." },
      ] },
      { heading: { en: "Your account", ar: "حسابك" }, body: [
        { en: "Keep your password private. Delivery and return codes are only for the courier, and only when the parcel is in your hands.", ar: "حافظ على سرية كلمة مرورك. رموز التسليم والإرجاع للمندوب فقط، وعندما يكون الطرد بين يديك." },
      ] },
      { heading: { en: "Reviews and questions", ar: "التقييمات والأسئلة" }, body: [
        { en: "Write honestly and respectfully. Only customers who received a product can review it. We may hide content that breaks these rules.", ar: "اكتب بصدق واحترام. لا يقيّم المنتج إلا من استلمه. وقد نخفي المحتوى المخالف لهذه القواعد." },
      ] },
      { heading: { en: "Returns, delivery and warranty", ar: "الإرجاع والتوصيل والضمان" }, body: [
        { en: "These are described in the returns and delivery policies, which are part of these terms.", ar: "موضحة في سياستي الإرجاع والتوصيل، وهما جزء من هذه الشروط." },
      ] },
      { heading: { en: "Changes", ar: "التغييرات" }, body: [
        { en: "We may update these terms. The date at the bottom shows the latest version; using the platform after a change means you accept it.", ar: "قد نحدّث هذه الشروط. يظهر التاريخ في الأسفل آخر نسخة، واستمرارك في الاستخدام بعد التغيير يعني قبولك له." },
      ] },
    ],
  },
  returns: {
    title: { en: "Returns and warranty", ar: "الإرجاع والضمان" },
    summary: { en: "How to cancel, return goods and claim under a warranty.", ar: "كيف تلغي وتُرجع البضاعة وتطالب بالضمان." },
    sections: [
      { heading: { en: "Cancel before it ships", ar: "الإلغاء قبل الشحن" }, body: [
        { en: "If your order has not shipped, cancel it from the order page and you are refunded at once.", ar: "إن لم يُشحن طلبك بعد فألغِه من صفحة الطلب ويُردّ إليك المبلغ فوراً." },
      ] },
      { heading: { en: "Return goods", ar: "إرجاع البضاعة" }, body: [
        { en: "You can ask to return goods within 14 days of delivery. We review the request; if it is approved you get a return code and choose a collection day.", ar: "يمكنك طلب إرجاع البضاعة خلال ١٤ يوماً من التسليم. نراجع الطلب، وإذا وُوفق عليه تحصل على رمز إرجاع وتختار يوم الاستلام." },
        { en: "Give the code to whoever collects the parcel. Once it reaches us it is inspected. If it passes, you are refunded in full to the way you paid. If it does not pass, you are told why and there is no refund.", ar: "أعطِ الرمز لمن يستلم الطرد. وعند وصوله إلينا يُفحص؛ فإن اجتاز الفحص يُردّ إليك المبلغ كاملاً بطريقة الدفع نفسها، وإن لم يجتز تُخبَر بالسبب ولا يُرد المبلغ." },
      ] },
      { heading: { en: "Services and installation", ar: "الخدمات والتركيب" }, body: [
        { en: "Services are handled through the job itself, with proof of the work and a time to object before the payment is released.", ar: "تُعالج الخدمات عبر المهمة نفسها، مع إثبات للعمل ومهلة للاعتراض قبل تحرير الدفعة." },
      ] },
      { heading: { en: "Warranty on goods", ar: "الضمان على البضاعة" }, body: [
        { en: "Products with a warranty show its length on the product page. If it fails while the warranty runs, make a claim from Warranties. The seller decides on a repair, a replacement or a refund. One claim at a time.", ar: "تعرض المنتجات ذات الضمان مدته في صفحة المنتج. وإذا تعطل أثناء سريان الضمان فقدّم مطالبة من صفحة «الضمانات». يقرر البائع إصلاحاً أو استبدالاً أو استرداداً. ومطالبة واحدة في كل مرة." },
      ] },
    ],
  },
  shipping: {
    title: { en: "Delivery policy", ar: "سياسة التوصيل" },
    summary: { en: "Fees, timing, the delivery code and what happens if nobody is home.", ar: "الرسوم والمواعيد ورمز التسليم وما يحدث إذا لم يكن أحد في المنزل." },
    sections: [
      { heading: { en: "Fees and timing", ar: "الرسوم والمواعيد" }, body: [
        { en: "The delivery fee for each seller is shown in your cart, and delivery is free above the amount shown where the market offers that. The product page and cart show when to expect your parcel. Where available you can choose express delivery for an extra fee.", ar: "تظهر رسوم التوصيل لكل بائع في سلتك، والتوصيل مجاني فوق المبلغ المعروض حيث تقدم السوق ذلك. وتعرض صفحة المنتج والسلة موعد وصول طردك. وحيث يتوفر يمكنك اختيار توصيل سريع برسوم إضافية." },
        { en: "You can ask for a preferred day and leave a gift note. A preferred day is a request, not a promise.", ar: "يمكنك طلب يوم مفضل وترك رسالة إهداء. واليوم المفضل طلب وليس وعداً." },
      ] },
      { heading: { en: "The delivery code", ar: "رمز التسليم" }, body: [
        { en: "When your parcel is out for delivery you get a code on your order page and in a notice. Give it to the courier only when you receive the parcel: it is the proof of delivery.", ar: "عندما يخرج طردك للتسليم تحصل على رمز في صفحة طلبك وفي إشعار. أعطه للمندوب عند استلام الطرد فقط، فهو إثبات التسليم." },
      ] },
      { heading: { en: "If nobody is home", ar: "إذا لم يكن أحد في المنزل" }, body: [
        { en: "After a failed attempt you choose a new day from your order. There are up to three attempts, after which you contact support.", ar: "بعد محاولة فاشلة تختار يوماً جديداً من صفحة طلبك. وهناك حتى ثلاث محاولات، وبعدها تتواصل مع الدعم." },
      ] },
      { heading: { en: "Cash on delivery", ar: "الدفع عند الاستلام" }, body: [
        { en: "Where it is offered, you can pay the courier in cash at the door. The order is paid once the parcel is delivered.", ar: "حيث يتوفر يمكنك الدفع نقداً للمندوب عند الباب. ويُعتبر الطلب مدفوعاً عند تسليم الطرد." },
      ] },
    ],
  },
  cookies: {
    title: { en: "Cookies and storage", ar: "ملفات الارتباط والتخزين" },
    summary: { en: "What the site stores in your browser and how to change your choice.", ar: "ما يخزنه الموقع في متصفحك وكيف تغيّر اختيارك." },
    sections: [
      { heading: { en: "Essential", ar: "الأساسية" }, body: [
        { en: "These keep you signed in and remember your cart and language. The site cannot work without them, so they are always on.", ar: "تُبقيك مسجلاً للدخول وتتذكر سلتك ولغتك. لا يعمل الموقع بدونها، لذلك تكون دائماً مفعّلة." },
      ] },
      { heading: { en: "Optional", ar: "الاختيارية" }, body: [
        { en: "Measuring how the site is used starts only if you accept. If you reject, nothing optional runs.", ar: "لا يبدأ قياس طريقة استخدام الموقع إلا إذا وافقت. وإن رفضت فلا يعمل شيء اختياري." },
      ] },
      { heading: { en: "What stays in your browser", ar: "ما يبقى في متصفحك" }, body: [
        { en: "Your language, your recently viewed products, your comparison list and your cookie choice are kept only in your own browser. They are not sent to us.", ar: "تُحفظ لغتك والمنتجات التي شاهدتها مؤخراً وقائمة المقارنة واختيارك للملفات في متصفحك أنت فقط، ولا تُرسل إلينا." },
      ] },
    ],
  },
};
