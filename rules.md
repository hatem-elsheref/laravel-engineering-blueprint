طب انا حاليا شغال لوحدي غالبا بشتغل لارافيل بس انا عاوز ابني الكود بسيط جدا بس ادفانسيد ازاي 
اولا الكلاسات عندي لازم كل حاجه تبقي ماشي بدورها ماينفعش اعمل كل حاجه ف كلاس لا لازم كل امكانيات الفريم ورك تستخدم


لازم مثلا المونترولر استخدمه استقبال وارسال الرد مفيش اي بيزنس هنا الفورم ريكويست لازم تفهم هيا بتتعامل مع فاليديشن واوثورايذزيشن ولازم كمان تفاليديت كل حاجه وهبا مخصصه للفاليديشن بطريقه محتركه بدون كاستم كود هيا فيها كل حاحه فيما تخص الرولز وترجمه الرسايل وقبل الاليديشن وخلافه وتعديل اليه الرد ف حاحله فالفيل فاليديشن  عندنا برضو DTO فاستقبال الريكويست وارسال الرد وعندنا resource colecion. - pagination - indexing - csrf -idemonnecy
any submit has diabled after nad idemnotenct
permfmce n slect2  useing ajax url and load more  when scrolling 


عندنا سيرفيس كلاس وعندنا اكشن كلاس وعندنتا فالراورتس api resource. - Route::resource nad sue only and except
وكمان استخدام احسن مود وaction class foreach method and action and alsysws be use resusable comnants nad api veriosjsng nad deisng pattern reduce  if condition use polymothigsm inehitance traigts  and solid prinsicepls dry nad kiss priinciples  and more nad gomain  and we need to make stub for any crud and nay modeuls also thathas some cruds inside it so we encepsualt ein a domain or anmesoce and all system funality will be folow one aproahc in dcodoing writing stuandrd nad lok simpel easy  and testable
…
أنت مش محتاج تكتب كود أكتر؛ أنت محتاج تبني Engineering Standard للمشروع كله
أنا فاهم أنت عايز توصل لإيه بالضبط.

أنت شغال غالبًا لوحدك في Laravel، وبتستخدم AI Coding Agents في التطوير، وعايز لما تطلب بناء Module أو CRUD جديد، النتيجة تكون:

الكود بسيط جدًا في القراءة.

كل Class مسؤول عن وظيفة واحدة واضحة.

استخدام إمكانيات Laravel الأصلية بدل اختراع حلول Custom.

مفيش Business Logic داخل Controllers.

مفيش Validation عشوائي.

مفيش تكرار في الكود.

كل Module ليه حدود ومسؤوليات واضحة.

كل العمليات قابلة للاختبار.

نفس أسلوب كتابة الكود في المشروع كله.

أي Developer أو AI Agent يقدر يفهم النظام ويكمل عليه بسهولة.

وده اتجاه ممتاز، خصوصًا إنك بتطور SaaS كبير وفيه Modules كتير.

لكن فيه نقطة عايز أصححها:

مش الهدف إننا نستخدم كل إمكانيات Laravel في كل مكان. الهدف إننا نستخدم الإمكانية المناسبة من Laravel في المكان الصحيح.

مش كل CRUD محتاج 10 Classes، ومش كل مشكلة محتاجة Design Pattern.

الاحتراف هنا إنك تخلي الـ Architecture واضحة، والـ Abstractions قليلة ومفيدة، والـ Business Rules محمية.

أنا أقترح نبني لك نظام هندسي نسميه:

Laravel Engineering Standard
Proposed Architecture
وهو عبارة عن مجموعة Standards + Architecture + Reusable Components + Code Generators + Automated Quality Gates.

1. أولًا: شكل الـ Architecture اللي هنلتزم بيه
خلينا نرسم دورة أي Request داخل النظام.

A Practical Guide to Clean Architecture in Laravel (or Any Modern PHP App) | by The Programmer Kid | Medium

Standard Laravel Request Lifecycle
API / Web Route

Routing, Middleware, Authentication

Form Request

Authorization + Validation + Normalization

Input DTO

Typed and structured input

Controller

Orchestration only — no business logic

Action Class

One business operation

Domain Services / Models / Policies

Business rules, persistence, transactions

Output DTO / API Resource

Structured and consistent response

مش لازم كل Request يستخدم كل طبقة، لكن ده الـ Standard الأساسي اللي هنشتغل عليه.

2. مسؤولية كل Class بالتحديد
دي أهم نقطة في النظام كله.

Class

مسؤوليته

ممنوع يعمل إيه؟

Controller

استقبال الطلب وتنسيق التنفيذ وإرجاع الرد

Business Logic

FormRequest

Validation وAuthorization

تنفيذ العمليات

DTO

نقل البيانات بشكل Typed

تنفيذ Business Logic

Action

تنفيذ عملية Business واحدة

التعامل مع HTTP Response

Service

منطق مشترك أو معقد داخل Domain

تجميع كل العمليات في Class واحد

Model

تمثيل البيانات والعلاقات وسلوك الكيان

إدارة Workflow كامل

Policy

تحديد الصلاحيات

تنفيذ العمليات

Resource

تحويل البيانات لشكل API

تنفيذ Queries عشوائية

Collection

تنسيق مجموعة Resources

إدارة Business Logic

Job

تنفيذ عمل بالخلفية

الاعتماد على Request مفتوح

Event

الإعلان عن حدوث شيء

تنفيذ كل النظام داخله

Listener

التعامل مع الحدث

خلط مسؤوليات Modules

خلينا نفصل كل واحدة.

2.1 Controller: استقبال وإرسال فقط
مثال:

final class StoreShipmentController
{
    public function __invoke(
        StoreShipmentRequest $request,
        StoreShipmentAction $action
    ): ShipmentResource {
        $shipment = $action->execute(
            StoreShipmentData::from($request)
        );

        return new ShipmentResource($shipment);
    }
}

لاحظ:

لا يوجد Validation.

لا يوجد Database Query.

لا يوجد Business Logic.

لا يوجد Transaction.

لا يوجد Calculation.

لا يوجد Authorization يدوي.

الـ Controller مسؤول عن تنسيق الـ HTTP Request والـ Response فقط.

ممكن تستخدم __invoke للعمليات المنفردة، أو Controller تقليدي للـ Resource Controllers.

2.2 Form Request: بوابة دخول البيانات
دي من أهم إمكانيات Laravel، ولازم نستغلها صح.

final class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('create', Shipment::class);
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],

            'carrier_id' => [
                'required',
                'integer',
                'exists:carriers,id',
            ],

            'service_code' => [
                'required',
                'string',
                'max:50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => __('validation.required'),
        ];
    }
}

لكن لازم نفهم إن FormRequest عنده إمكانيات أكثر من كده:

Method

وظيفته

authorize()

Authorization

rules()

Validation Rules

messages()

رسائل مخصصة

attributes()

أسماء الحقول المترجمة

prepareForValidation()

تعديل البيانات قبل Validation

validationData()

تحديد البيانات التي يتم التحقق منها

passedValidation()

التعامل مع البيانات بعد نجاح Validation

withValidator()

إضافة سلوك للـ Validator

after()

إضافة عمليات تحقق بعد القواعد الأساسية

مثال:

public function prepareForValidation(): void
{
    $this->merge([
        'service_code' => strtoupper(
            trim((string) $this->input('service_code'))
        ),
    ]);
}

وفي الـ Rules استخدم الإمكانيات الأصلية:

required

sometimes

nullable

required_if

prohibited_if

exists

unique

Rule::in()

Rule::exists()

Rule::unique()

Rule::when()

after

before

date_format

decimal

array

required_array_keys

كمان لازم نفهم إن الـ Validation مش بديل عن Business Rules.

مثلًا التحقق إن warehouse_id موجود مش معناه إن المستخدم مسموح له يتعامل مع المخزن ده.

دي مسؤولية Authorization وDomain Logic.

2.3 DTO: نقل البيانات بطريقة Typed
أنت ذكرت إنك عايز DTO للاستقبال والإرسال، وده اختيار جيد.

لكن مهم نحدد نوعين:

Input DTO.

Output DTO.

مثال باستخدام PHP:

final readonly class StoreShipmentData
{
    public function __construct(
        public int $orderId,
        public int $carrierId,
        public string $serviceCode,
    ) {}

    public static function from(
        StoreShipmentRequest $request
    ): self {
        return new self(
            orderId: $request->integer('order_id'),
            carrierId: $request->integer('carrier_id'),
            serviceCode: $request->string('service_code')->toString(),
        );
    }
}

الـ Action مش محتاج يعرف أي حاجة عن HTTP Request.

وده معناه إنك تقدر تستدعيه من:

API Controller.

Web Controller.

Queue Job.

Command.

Event Listener.

Another Action.

من غير ما تغير الـ Business Logic.

ملحوظة: لو عايز تستخدم spatie/laravel-data أو مكتبة DTO، نقدر ندرسها، لكن مش لازم نضيف Dependency لمجرد إنشاء DTO بسيط.

3. Action Classes وService Classes
دي من أهم القرارات المعمارية اللي لازم نوحدها.

فيه فرق كبير بين Action وService.

3.1 Action Class
الـ Action مسؤول عن تنفيذ عملية Business واحدة.

أمثلة في Shipment Module:

Actions/
├── CreateShipmentAction.php
├── CancelShipmentAction.php
├── UpdateShipmentAddressAction.php
├── AssignCarrierAction.php
└── CalculateShipmentCostAction.php
كل Action له وظيفة واضحة.

مثال:

final class CreateShipmentAction
{
    public function execute(
        StoreShipmentData $data
    ): Shipment {
        return DB::transaction(function () use ($data) {
            $order = Order::query()
                ->findOrFail($data->orderId);

            return Shipment::query()->create([
                'order_id' => $order->id,
                'carrier_id' => $data->carrierId,
                'service_code' => $data->serviceCode,
            ]);
        });
    }
}

ده مثال تعليمي، وفي التطبيق الفعلي هنضيف قواعد التحقق من الطلب والمخزون ومنع التكرار حسب الـ Business Requirements.

3.2 Service Class
الـ Service بنستخدمه لما يكون عندنا منطق مشترك أو مجموعة عمليات مترابطة داخل Domain معين.

مثلاً:

Shipment/
├── Actions/
│   ├── CreateShipmentAction.php
│   ├── CancelShipmentAction.php
│   └── CalculateShipmentCostAction.php
│
└── Services/
    ├── ShipmentPricingService.php
    ├── ShipmentTrackingService.php
    └── ShipmentStatusService.php
الـ ShipmentPricingService ممكن يكون مسؤول عن حساب التكلفة، ويُستخدم بواسطة أكثر من Action.

الفرق باختصار
Action

Service

عملية محددة

منطق قابل لإعادة الاستخدام

له نقطة تنفيذ واضحة

ممكن يقدم عدة Methods مترابطة

مثل Create Shipment

مثل Pricing Engine

غالبًا له Execute واحدة

ممكن يحتوي عدة Methods

مش قاعدة جامدة في Laravel، لكن ده Convention كويس نقدر نعتمده.

3.3 هل نستخدم Action لكل Method؟
مش بالضرورة.

مثلاً:

لو عندك Model فيه عمليات بسيطة جدًا زي تغيير اسم أو تحديث وصف، مش لازم تعمل Action منفصل لكل Setter.

لكن لو العملية فيها Business Rules أو صلاحيات أو Transactions أو Side Effects، الأفضل يكون لها Action واضح.

الهدف مش كثرة الكلاسات؛ الهدف إن كل Class يكون له سبب واضح للوجود.

4. API Resources وResource Collections
دي نقطة مهمة جدًا.

لازم نمنع إن كل Controller يرجع JSON بشكل مختلف.

Laravel عنده API Resources علشان توحّد شكل الاستجابة.

مثال:

final class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'carrier' => [
                'id' => $this->carrier?->id,
                'name' => $this->carrier?->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

وعند عرض مجموعة:

return ShipmentResource::collection(
    $shipments
);

وعند Pagination:

return ShipmentResource::collection(
    Shipment::query()
        ->with('carrier')
        ->latest()
        ->paginate(20)
);

Laravel بيتعامل مع Pagination Metadata تلقائيًا في Resource Collections.

لكن لازم نحط Standard موحد للـ API Response.

مثلاً:

{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  },
  "links": {}
}

ولو فيه Error:

{
  "message": "The given data was invalid.",
  "errors": {
    "order_id": [
      "The order id field is required."
    ]
  }
}

نستخدم آلية Laravel الافتراضية ونخصصها عند الحاجة بدل بناء Response System كامل من الصفر.

5. Routes: استخدام Laravel Resource Routes بشكل احترافي
أنا متفق معاك إننا لازم نوحد طريقة تعريف الـ Routes.

بدل:

Route::get('/shipments', ...);
Route::post('/shipments', ...);
Route::get('/shipments/{shipment}', ...);
Route::put('/shipments/{shipment}', ...);
Route::delete('/shipments/{shipment}', ...);

نستخدم:

Route::apiResource(
    'shipments',
    ShipmentController::class
);

ولو عايزين نستثني بعض العمليات:

Route::apiResource('shipments', ShipmentController::class)
    ->only(['index', 'store', 'show']);

Route::apiResource('shipments', ShipmentController::class)
    ->except(['destroy']);

لكن خد بالك إن المثالين بدائل لبعض، مش بنكتبهم مع بعض لنفس الـ Resource.

API Versioning
Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::apiResource(
            'shipments',
            ShipmentController::class
        );
    });

ومهم جدًا إن Versioning مش مجرد Prefix.

لازم يكون عندك استراتيجية للتعامل مع اختلافات:

API Contracts.

Resources.

Request Validation.

Deprecation.

Backward Compatibility.

6. Security: CSRF وAuthorization وIdempotency
دي لازم تكون جزء من الـ Standard، مش إضافات اختيارية.

6.1 CSRF
CSRF اختصار Cross-Site Request Forgery.

هدفه حماية المستخدم من موقع خارجي يحاول ينفذ Request باستخدام Session المستخدم.

في Laravel:

Web Routes بتستخدم CSRF Protection من خلال Middleware المناسب.

API Routes المعتمدة على Bearer Tokens لا تعتمد عادةً على CSRF بنفس الطريقة.

لو API بيعتمد على Cookies وSession Authentication، لازم تتعامل مع CSRF بشكل صحيح، مثل إعدادات Sanctum المناسبة.

ممنوع تعطيل CSRF بشكل عشوائي علشان Request يشتغل.

6.2 Idempotency
دي مهمة جدًا في أنظمة الشحن والدفع والمخازن.

يعني لو المستخدم بعت نفس العملية مرتين، النظام ما ينفذهاش مرتين.

مثال:

المستخدم ضغط Create Shipment، والـ Request اتنفذ، لكن الإنترنت فصل قبل ما الرد يوصل.

المستخدم ضغط تاني.

لو مفيش Idempotency، ممكن تعمل شحنتين لنفس الطلب.

الحل:

POST /api/v1/shipments

Idempotency-Key: unique-operation-key
النظام يسجل:

Tenant ID.

User ID أو Client Identity حسب التصميم.

Idempotency Key.

Request Fingerprint.

Processing Status.

Response أو Resource Reference.

Expiration Time.

والـ Key لازم يكون عليه Unique Database Constraint بالنطاق الصحيح.

لو نفس الـ Key اتبعت بنفس البيانات، نرجع نفس النتيجة وفق سياسة النظام.

لو اتبعت ببيانات مختلفة، نرفضها.

مهم: Idempotency مش مجرد منع الضغط مرتين من الواجهة. دي حماية Backend وDatabase.

6.3 Submit Button Disable
في Frontend:

let submitting = false;

async function submitForm() {
    if (submitting) return;

    submitting = true;

    try {
        await saveShipment();
    } finally {
        submitting = false;
    }
}

ده بيمنع الضغط المتكرر داخل نفس الواجهة.

لكن مش بديل عن Idempotency.

الأفضل كمان:

Disable أثناء الإرسال.

Loading State.

منع Double Submit.

Idempotency Key على Backend.

Database Constraints عند العمليات الحساسة.

7. Performance: Pagination وIndexing وSelect2
هنا لازم نفكر كمهندسين Backend وFrontend مع بعض.

7.1 Pagination
ممنوع ترجع كل السجلات في صفحة واحدة.

Laravel يوفر:

Method

الاستخدام

paginate()

Pagination مع العدد الإجمالي

simplePaginate()

Pagination بدون حساب العدد الإجمالي

cursorPaginate()

Pagination باستخدام Cursor

مثال:

Shipment::query()
    ->with('carrier')
    ->latest('id')
    ->paginate(20);

ولو عندك ملايين السجلات وتصفح مستمر:

Shipment::query()
    ->orderBy('id')
    ->cursorPaginate(50);

لكن اختيار النوع يعتمد على احتياج الواجهة، وترتيب النتائج، والفلاتر.

7.2 Database Indexing
مش كل Column لازم يكون عليه Index.

مثال:

لو عندك استعلام:

SELECT *
FROM shipments
WHERE tenant_id = 10
AND status = 'pending'
ORDER BY created_at DESC;

ممكن تحتاج Composite Index:

CREATE INDEX shipments_tenant_status_created_idx
ON shipments (tenant_id, status, created_at);

لكن ترتيب الأعمدة لازم يتحدد حسب الاستعلامات الفعلية ونظام قاعدة البيانات.

لازم نتعلم:

Primary Index.

Unique Index.

Composite Index.

Covering Index.

Query Plans.

EXPLAIN.

N+1 Queries.

Eager Loading.

ومش هنضيف Index لمجرد إن العمود موجود. لازم نراجع Query Patterns ونقيس الأداء.

7.3 Select2 باستخدام AJAX وInfinite Scroll
متفق معاك جدًا إن Dropdown فيه آلاف أو ملايين السجلات لازم يستخدم AJAX.

javascript - How to make ajax call and get results back while typing in input text - Stack Overflow

مثلاً اختيار Merchant أو Order أو Product.

Frontend:

$('#merchant_id').select2({
    ajax: {
        url: '/api/v1/merchants/options',
        dataType: 'json',
        delay: 300,

        data: function (params) {
            return {
                search: params.term,
                page: params.page || 1
            };
        },

        processResults: function (response) {
            return {
                results: response.data,
                pagination: {
                    more: response.meta.current_page
                        < response.meta.last_page
                }
            };
        }
    },

    minimumInputLength: 2
});

Backend:

public function __invoke(
    MerchantOptionsRequest $request,
    GetMerchantOptionsAction $action
): AnonymousResourceCollection {
    return MerchantOptionResource::collection(
        $action->execute(
            MerchantOptionsData::from($request)
        )
    );
}

والـ Query يكون:

Merchant::query()
    ->select(['id', 'name'])
    ->when(
        $data->search,
        fn ($query) => $query->where(
            'name',
            'like',
            "%{$data->search}%"
        )
    )
    ->orderBy('name')
    ->paginate(20);

في التطبيق الحقيقي نراعي كمان:

Tenant Scope.

Authorization.

Debouncing.

Search Length.

Query Indexing.

Minimum Input Length.

Pagination Metadata.

اختيار العنصر الحالي عند Edit.

8. SOLID وDRY وKISS وDesign Patterns
دي المبادئ اللي هتخلينا نكتب كود بسيط وقابل للتطوير.

SOLID
المبدأ

معناه

تطبيقه

S

Single Responsibility

كل Class له مسؤولية محددة

O

Open/Closed

نضيف سلوك جديد بدون تكسير الموجود

L

Liskov Substitution

الأنواع الفرعية تحافظ على توقعات النوع الأساسي

I

Interface Segregation

Interfaces صغيرة ومتخصصة

D

Dependency Inversion

نعتمد على Abstractions عند الحاجة

DRY
Don't Repeat Yourself.

لو عندك نفس Business Rule متكررة في 5 أماكن، لازم تفكر توحدها.

لكن مش معنى DRY إننا نحول كل سطر متشابه إلى Helper عام.

أحيانًا التكرار البسيط أفضل من Abstraction معقد.

KISS
Keep It Simple, Stupid.

مثلاً:

بدل ما تعمل:

ShipmentManagerFactoryResolverHandler
علشان تنفذ عملية بسيطة، استخدم:

CreateShipmentAction
YAGNI
You Aren't Gonna Need It.

متبنيش Abstraction لمشكلة لسه مش موجودة.

Polymorphism
بدل سلسلة طويلة من شروط:

if ($carrier === 'aramex') {
    // ...
} elseif ($carrier === 'smsa') {
    // ...
} elseif ($carrier === 'dhl') {
    // ...
}

ممكن تستخدم Interface:

interface CarrierDriver
{
    public function createShipment(
        ShipmentData $data
    ): CarrierShipmentResult;
}

ثم:

final class AramexDriver implements CarrierDriver
{
    public function createShipment(
        ShipmentData $data
    ): CarrierShipmentResult {
        // Aramex integration
    }
}

final class SmsaDriver implements CarrierDriver
{
    public function createShipment(
        ShipmentData $data
    ): CarrierShipmentResult {
        // SMSA integration
    }
}

وتستخدم Driver Registry أو Laravel Container لحل الـ Implementation المناسب.

لكن مش كل if غلط. لو عندك شرطين بسيطين، ممكن الشرط يكون أوضح من Pattern كامل.

Traits
نستخدم Traits لما يكون عندنا سلوك صغير قابل لإعادة الاستخدام بين Classes، وليس لتجميع Business Logic عشوائيًا.

مثلاً:

HasUuid.

HasTenantScope (إذا كان مناسبًا لنموذج الصلاحيات).

HasAuditMetadata.

لكن خلي بالك من Traits اللي بتخفي Queries أو Transactions أو Dependencies؛ لأنها بتصعب فهم الكود.

9. Domain-Based Modular Architecture
دي النقطة اللي هتجمع كل الكلام السابق في Structure واحدة.

أنا أرشح ليك Modular Monolith باستخدام Domain-Oriented Modules.

يعني بدل ما المشروع كله يبقى متقسم تقليديًا حسب نوع الكلاس فقط، نخلي كل Module مسؤول عن Business Domain محدد.

الشكل المقترح
app/
│
├── Modules/
│   │
│   ├── Shipments/
│   │   ├── Actions/
│   │   ├── DTOs/
│   │   │   ├── Inputs/
│   │   │   └── Outputs/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Policies/
│   │   ├── Events/
│   │   ├── Listeners/
│   │   ├── Jobs/
│   │   ├── Queries/
│   │   ├── Enums/
│   │   ├── Exceptions/
│   │   ├── Database/
│   │   │   ├── Migrations/
│   │   │   ├── Factories/
│   │   │   └── Seeders/
│   │   ├── Routes/
│   │   │   └── api.php
│   │   └── Providers/
│   │
│   ├── Inventory/
│   ├── Orders/
│   ├── Merchants/
│   ├── Warehouses/
│   ├── Carriers/
│   └── Billing/
│
├── Shared/
│   ├── DTOs/
│   ├── Exceptions/
│   ├── Support/
│   ├── Traits/
│   └── Enums/
│
└── Providers/
ده تصور مبدئي، مش لازم كل Module يحتوي كل المجلدات.

مثلًا لو Module بسيط، ممكن يحتوي فقط:

Categories/
├── Actions/
├── DTOs/
├── Http/
├── Models/
└── Routes/
أهم قاعدة في Modular Architecture
كل Domain مسؤول عن بياناته وعملياته.

مثلاً:

Inventory Module هو المسؤول عن تعديل المخزون.

Shipment Module لا يعدل المخزون مباشرة من خلال Query عشوائي على جداول Inventory.

بدل ذلك، يتعامل مع عملية واضحة من Inventory Domain.

كمان مش عايزين كل Module يعمل Import لكل تفاصيل Module آخر.

لازم يكون عندنا قواعد واضحة للتواصل بين الـ Modules باستخدام Actions أو Services أو Events حسب نوع العلاقة.

ملحوظة مهمة: الـ Modular Monolith مش معناه إننا بنبني Microservices داخل Laravel. إحنا بنبني تطبيق واحد، لكن بحدود داخلية واضحة.

10. أهم جزء: CRUD Stub Generator
دي من الأفكار اللي هتوفر عليك وقت كبير جدًا، خصوصًا مع AI Coding Agents.

بدل ما كل مرة تطلب من Cursor:

"اعمل CRUD جديد بنفس الطريقة اللي اتفقنا عليها."

نعمل Laravel Stubs موحدة.

10.1 مثال على إنشاء Module
تكتب:

php artisan make:module Products

يتولد:

Modules/Products/
├── Actions/
│   ├── CreateProductAction.php
│   ├── UpdateProductAction.php
│   ├── DeleteProductAction.php
│   └── ListProductsAction.php
│
├── DTOs/
│   ├── Inputs/
│   │   ├── StoreProductData.php
│   │   └── UpdateProductData.php
│   └── Outputs/
│
├── Http/
│   ├── Controllers/
│   │   └── ProductController.php
│   ├── Requests/
│   │   ├── StoreProductRequest.php
│   │   └── UpdateProductRequest.php
│   └── Resources/
│       └── ProductResource.php
│
├── Models/
│   └── Product.php
│
├── Policies/
│   └── ProductPolicy.php
│
├── Database/
│   └── Migrations/
│
└── Routes/
    └── api.php
10.2 إنشاء CRUD داخل Module
php artisan make:module-crud Products Product

والـ Command ينشئ:

Model.

Migration.

Factory.

Seeder اختياري.

Form Requests.

Input DTOs.

Actions.

Controller.

API Resource.

Policy.

Routes.

Tests.

لكن لازم الـ Generator يكون ذكيًا، مش مجرد نسخ ملفات.

مثلاً يدعم:

php artisan make:module-crud Products Product \
    --api \
    --soft-deletes \
    --search \
    --pagination \
    --policy \
    --tests

10.3 الـ Stubs نفسها
نحفظ Templates داخل:

stubs/
├── module.stub
├── model.stub
├── controller.stub
├── request.stub
├── dto.stub
├── action.stub
├── resource.stub
├── policy.stub
├── migration.stub
├── route.stub
└── test.stub
ونستخدم Laravel Artisan Generator وreplace وpublish عند الحاجة.

مش عايزين كل مرة ننسخ الكود يدويًا.

10.4 لكن لازم نراعي أنواع الـ CRUD
مش كل Module نفس الشكل.

مثلاً:

النوع

الاختلاف

Basic CRUD

Create, Read, Update, Delete

Catalog CRUD

Search, Filters, Options, Pagination

Transactional CRUD

Transactions, Audit, State Transitions

Master Data CRUD

Unique Rules, Soft Deletes

Nested CRUD

Parent-Child Relationships

Workflow CRUD

Approvals, Events, State Management

فمحتاجين Generator يدعم Profiles أو Presets.

مثلاً:

php artisan make:module-crud Inventory Adjustment \
    --profile=transactional

وده يولد Structure مناسبة لعمليات المخزون بدل CRUD تقليدي.

11. Standard لازم يكون موجود في كل Module
أنا أقترح نعتمد Checklist موحدة.

Module Quality Checklist

25/26
Architecture


كل Class له مسؤولية محددة

لا يوجد Business Logic في Controller

كل عملية Business معقدة لها Action واضح

التعامل بين Domains من خلال Contracts واضحة
Validation & Security


كل Input له Form Request مناسب

Authorization من خلال Policies أو آلية معتمدة

Validation Rules مترجمة

Tenant Isolation مطبق

لا يوجد Mass Assignment غير محمي
Data & Performance


استخدام DTO للبيانات عند الحاجة

API Resources للردود

Pagination للقوائم

Eager Loading لمنع N+1

Indexing مبني على Query Patterns

Transactions للعمليات الذرية
Frontend & API


API Versioning

Consistent API Responses

AJAX Select2 عند القوائم الكبيرة

Loading وDisabled States

Idempotency للعمليات الحساسة
Quality


Feature Tests

Unit Tests عند الحاجة

Static Analysis

Coding Standards

No duplicated business rules

Documentation عند الحاجة
12. إزاي نخلي AI يلتزم بالـ Standard ده؟
ودي نقطة مهمة جدًا بالنسبة لطريقة شغلك.

أنا مش عايزك تعتمد على Prompt طويل تقول فيه كل مرة:

"استخدم DTO وAction وResource ومتعملش Business Logic في Controller."

لأن ده مش نظام هندسي ثابت.

عايزين نعمل 4 طبقات:

AI Coding Governance
1. Engineering Constitution

القواعد الأساسية الثابتة للمشروع.

2. Architecture Documentation

توثيق Domains وLayers والعلاقات بينها.

3. Skills & Workflows

تعليمات متخصصة لبناء CRUD وAPI وServices وTesting.

4. Automated Enforcement

Tests وStatic Analysis وLinters وArchitecture Tests وCI.

مثلاً نعمل Skill باسم:

laravel-module-development

تحتوي على:

كيفية إنشاء Module.

Naming Conventions.

Controller Standards.

Form Request Standards.

DTO Standards.

Action Standards.

Resource Standards.

Testing Standards.

ممنوعات Architecture.

لكن الأهم إن القواعد المهمة تكون قابلة للتحقق آليًا، مش مجرد تعليمات للـ AI.

أدوات مهمة:

Laravel Pint.

PHPStan / Larastan.

PHPUnit أو Pest.

PHP Architecture Testing.

Deptrac عند الحاجة لفحص حدود الـ Modules.

Laravel IDE Helper عند الحاجة.

13. قرارات إضافية أنصح بإضافتها للـ Standard
فيه حاجات أنت ما ذكرتهاش لكنها ضرورية لبناء نظام احترافي:

المجال

Standard المقترح

Database

Migrations وConstraints وTransactions

Authorization

Policies وGates

Queries

Query Objects عند تعقيد البحث

Background Work

Jobs وQueues

Domain Events

Events وListeners

Integrations

Dedicated Clients وAdapters

Error Handling

Custom Domain Exceptions

Logging

Structured Logging

Audit

Audit Trail للعمليات الحساسة

Caching

Cache Strategy وInvalidation

API

OpenAPI Documentation

Testing

Factories وFeature Tests

Architecture

Module Boundaries

Reliability

Idempotency وRetry Policies

وفي Laravel تحديدًا، لازم ندرس ونستخدم حسب الحاجة:

Service Container.

Service Providers.

Dependency Injection.

Middleware.

Policies.

Gates.

Scopes.

Observers.

Events.

Listeners.

Notifications.

Jobs.

Queues.

Cache.

Rate Limiting.

API Resources.

Form Requests.

Route Model Binding.

Eloquent Relationships.

Database Transactions.

14. اقتراحي النهائي: نبني Laravel Architecture خاصة بيك
أنا شايف إننا بدل ما نبدأ بتعديل مشروع موجود مباشرة، نبني Architecture Standard متكاملة ونجربها على Module حقيقي.

Laravel Engineering Blueprint
Recommended direction
المكونات اللي هننتجها:

Architecture Specification: تعريف كل Layer ومسؤولياتها.
Module Structure: تنظيم Domains والـ Modules.
CRUD Generator: توليد الكود من Laravel Stubs.
API Standard: Requests وDTOs وResources وPagination.
Security Standard: Policies وCSRF وIdempotency وTenant Isolation.
Frontend Standard: AJAX Select2 وReusable Components وSubmit Protection.
Testing Standard: Tests لكل نوع من العمليات.
AI Coding Skills: تعليمات قابلة لإعادة الاستخدام للـ Coding Agents.
CI Quality Gates: منع دمج الكود المخالف للمعايير.
لكن قبل ما نصمم الـ Blueprint، محتاج نحسم 4 قرارات معاك
1. شكل الـ Architecture


Modular Monolith – كل Domain مستقل داخل Modules (ترشيحي)

Laravel Traditional Structure – app/Http, app/Models, app/Services

Hybrid – Modules للـ Business Domains مع Shared Infrastructure
2. نظام DTO


Native PHP DTOs – بدون Dependencies إضافية

Spatie Laravel Data – DTO + Validation + Transformation

Hybrid – FormRequest للـ Validation وDTOs للـ Data Transfer
3. مستوى CRUD Generator


Basic CRUD Generator

Advanced Generator – Profiles للـ CRUD والـ Workflows والـ Transactions
4. Frontend


Laravel Blade + JavaScript + Select2

Livewire

Vue + Laravel API

Blade + Vue حسب احتياج كل Module
نصيحة أخيرة: بما إنك بتشتغل لوحدك وبتستخدم AI بكثافة، أهم حاجة نبني Architecture تخلي الـ AI ينتج كود متوقع ومتكرر الجودة، بدل ما كل مرة يخترع طريقة جديدة. البساطة هنا مش قلة Classes؛ البساطة إنك لما تفتح أي Module تعرف مكان كل حاجة فورًا، وتعرف مين مسؤول عن إيه، وتقدر تختبره وتغيره بدون ما تكسر بقية النظام.

اعتمد القرارات التالية لتصميم Laravel Engineering Blueprint بالتفصيل: Architecture: modular، DTO: native، CRUD Generator: advanced، Frontend: mixed. أريد Specification احترافية، Folder Structure، Coding Standards، أمثلة كاملة، Laravel Stubs، Artisan Generator، Testing وAI Coding Rules. لا تبدأ بكتابة كل الكود مرة واحدة؛ صمم النظام أولاً ثم نفذه على مراحل.
Laravel Engineering Blueprint
Architecture approved Native DTO Advanced Generator Mixed Frontend

هنبني النظام باعتباره Engineering Standard للمشروع كله، مش مجرد Folder Structure أو مجموعة قواعد شكلية.

الهدف إن أي Developer أو AI Agent يقدر يضيف Feature جديدة بنفس الطريقة، من غير ما يخترع Architecture مختلفة كل مرة، ومن غير ما يتحول المشروع إلى مئات الكلاسات الفارغة.

المبدأ الأساسي:

Simple to read. Strict in responsibility. Native to Laravel. Easy to test. Consistent by design.

هصمم الـ Blueprint على أساس Laravel 13 وPHP 8.3، مع Modular Monolith، وبدون الاعتماد الإجباري على Packages للـ DTO أو CRUD. هنستفيد من Laravel نفسه، ونضيف Abstractions فقط لما يكون لها قيمة فعلية.

1. القرارات المعمارية النهائية
القرار

الاختيار

النتيجة

Architecture

Modular

كل Business Domain له Module مستقل

DTO

Native PHP

readonly DTOs بدون Package

CRUD Generator

Advanced

Artisan Generator مبني على Stubs وProfiles

Frontend

Mixed

Blade + Livewire + Vue حسب طبيعة الشاشة

Backend style

Laravel-native

استخدام Framework features قبل بناء بدائل

Testing

Test-first for critical flows

اختبارات لكل العمليات المهمة

API

Versioned

عقود API واضحة وقابلة للتطور

Multi-tenancy

Tenant-aware by design

عزل البيانات على مستوى كل Module

قواعد لا نتنازل عنها
Controller لا يحتوي Business Logic.

FormRequest هي مدخل الـ HTTP Validation وAuthorization.

كل عملية Business واضحة لها نقطة دخول محددة.

لا نستخدم Service لمجرد نقل الكود من Controller.

لا نستخدم Repository Pattern تلقائيًا مع كل Eloquent Model.

لا نستخدم Traits لإخفاء Business Logic.

كل Module مسؤول عن بياناته وقواعده.

أي Cross-module communication يكون واضحًا ومحدودًا.

كل Query مهمة يجب أن تكون قابلة للقياس والتحسين.

لا نولد ملفات لا يحتاجها الـ Feature فعلًا.

2. Architecture: Modular Monolith
هنستخدم Modular Monolith، وليس Microservices.

يعني التطبيق يفضل Laravel Application واحدة، لكن داخليًا مقسّم إلى Modules مستقلة منطقيًا. كل Module يحتوي الـ Business Logic والـ HTTP Interfaces والـ Data Access والاختبارات الخاصة به.

2.1 Module boundaries
في نظام Fulfillment/WMS مثلًا:

Application
Identity

Independent module

Merchants

Independent module

Orders

Independent module

Inventory

Independent module

Warehouses

Independent module

Shipments

Independent module

Carriers

Independent module

Billing

Independent module

Integrations

Independent module

Notifications

Independent module

Shared Kernel

Common primitives, shared contracts, base exceptions, and infrastructure abstractions.

كل Module مسؤول عن:

قواعد الـ Business الخاصة به.

Models وMigrations الخاصة ببياناته.

عمليات القراءة والكتابة.

API وWeb endpoints الخاصة به عند الحاجة.

Policies وEvents وJobs.

Tests الخاصة به.

مهم: وجود Module لا يعني إننا لازم ننشئ كل أنواع الملفات السابقة. ننشئ فقط ما يحتاجه الـ Feature.

2.2 Folder Structure
ده الشكل المستهدف:

app/
├── Modules/
│   ├── Orders/
│   │   ├── Domain/
│   │   │   ├── Enums/
│   │   │   ├── Events/
│   │   │   ├── Exceptions/
│   │   │   ├── Rules/
│   │   │   └── ValueObjects/
│   │   │
│   │   ├── Application/
│   │   │   ├── Actions/
│   │   │   ├── DTOs/
│   │   │   ├── Queries/
│   │   │   └── Services/
│   │   │
│   │   ├── Infrastructure/
│   │   │   ├── Integrations/
│   │   │   ├── Jobs/
│   │   │   └── Listeners/
│   │   │
│   │   ├── Presentation/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   ├── Requests/
│   │   │   │   ├── Resources/
│   │   │   │   └── Middleware/
│   │   │   └── Console/
│   │   │
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Providers/
│   │   ├── routes/
│   │   │   ├── api.php
│   │   │   └── web.php
│   │   └── Database/
│   │       ├── Migrations/
│   │       ├── Factories/
│   │       └── Seeders/
│   │
│   └── Inventory/
│       └── ...
│
├── Shared/
│   ├── Contracts/
│   ├── Data/
│   ├── Enums/
│   ├── Exceptions/
│   ├── Support/
│   └── Traits/
│
└── Providers/
    └── ModuleServiceProvider.php

resources/
├── views/
│   ├── layouts/
│   └── components/
├── js/
│   ├── app.js
│   ├── components/
│   └── modules/
│       ├── orders/
│       └── inventory/
└── css/

routes/
├── web.php
└── api.php

database/
└── ...

tests/
├── Architecture/
├── Feature/
├── Integration/
└── Support/

stubs/
└── modules/
    ├── module.stub
    ├── model.stub
    ├── migration.stub
    ├── controller.stub
    ├── request.stub
    ├── dto.stub
    ├── action.stub
    ├── resource.stub
    ├── policy.stub
    ├── factory.stub
    ├── feature-test.stub
    └── ...
2.3 وظيفة كل Layer
Layer

مسؤوليته

ممنوع عليه

Domain

Business rules, invariants, value objects

HTTP, Blade, Request

Application

Use cases, Actions, Queries, orchestration

عرض HTML أو HTTP response

Infrastructure

External APIs, queues, integrations

امتلاك قواعد الـ Business الأساسية

Presentation

Controllers, Requests, Resources

تنفيذ Business Logic

Models

Eloquent persistence وعلاقات البيانات

إدارة Use Cases كاملة

Shared

عناصر عامة مشتركة فعلًا

وضع أي كود لمجرد إعادة استخدامه

قاعدة مهمة: لا نحتاج إلى تطبيق Clean Architecture حرفيًا في كل مكان. نحتاج فصلًا واضحًا للمسؤوليات، مع الاحتفاظ بقوة Laravel وEloquent وContainer.

2.4 حدود الاعتماد بين الـ Modules
الـ Module لا يستورد Controller أو Request من Module آخر.

لا نستخدم Model داخلي من Module آخر كأنه جزء من نفس الـ Module دون اتفاق واضح.

التواصل يكون عبر Public Application Actions أو Contracts أو Domain Events حسب نوع العلاقة.

لا نسمح بـ Circular Dependencies.

لا ننشئ Shared class لمجرد أن كلاس واحد يستخدمه Module آخر.

مثال: Orders يحتاج حجز مخزون من Inventory. لا يدخل إلى Controller أو تفاصيل تنفيذ Inventory؛ يستدعي Use Case معلنًا مثل ReserveStockAction أو Contract مخصصًا لذلك.

3. Request Lifecycle: الشكل الموحد
كل HTTP operation تتبع المسار المناسب لها:

01

Route

تحديد endpoint وmiddleware وroute model binding

02

Middleware

Authentication, tenant context, throttling وغيرها

03

FormRequest

Authorization + input preparation + validation

04

Input DTO

تحويل البيانات المعتمدة إلى typed input

05

Controller

تنسيق العملية وربط HTTP بالـ Application

06

Action / Query

تنفيذ Use Case أو قراءة البيانات

07

Model / Domain / Infrastructure

قواعد البيانات والتعاملات والتكاملات

08

Resource / View

تحويل النتيجة إلى API JSON أو HTML

المسار ده مش شرط يكون فيه DTO لكل Request، ولا Action لكل Endpoint. الـ Generator هيولد النمط المتفق عليه، لكن يسمح بملفات اختيارية حسب نوع الـ Feature.

4. Coding Standards
4.1 Controller Standards
Controller مسؤول عن:

استقبال Request.

استدعاء Action أو Query.

اختيار HTTP status المناسب.

إرجاع Resource أو View أو Redirect.

ممنوع:

DB::transaction() داخل Controller.

حساب الأسعار أو المخزون.

استخدام if لتحديد Business Rules.

بناء Queries طويلة.

إرسال HTTP Requests إلى Carrier.

كتابة أكثر من Use Case داخل Method واحدة.

مثال:

final class StoreOrderController
{
    public function __invoke(
        StoreOrderRequest $request,
        StoreOrderAction $action,
    ): OrderResource {
        $order = $action->handle(
            StoreOrderData::from($request)
        );

        return new OrderResource($order);
    }
}

لو الـ Endpoint يحتاج Response مخصصًا، نستخدم response() أو Resource حسب الاتفاق. المهم أن الـ Controller لا يصبح مكانًا لتنفيذ العملية.

4.2 FormRequest Standards
FormRequest هي المصدر الأساسي لـ HTTP input validation وauthorization.

final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('create', Order::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => trim(
                (string) $this->input('reference')
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:100',
            ],
            'merchant_id' => [
                'required',
                'integer',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.sku' => [
                'required',
                'string',
                'max:100',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('orders.validation.items_required'),
        ];
    }
}

قواعد إضافية:

نستخدم prepareForValidation() للتطبيع، وليس لتنفيذ Business Logic.

نستخدم authorize() مع Policies أو Gates.

نستخدم after() لقواعد Validation المركبة التي تعتمد على أكثر من field.

لا نكرر التحقق نفسه في Controller.

لا نعتمد على exists وحدها لعزل الـ Tenant؛ يجب تقييد السجلات بسياق الـ Tenant والتحقق من الملكية.

رسائل الـ Validation في ملفات ترجمة، وليست نصوصًا ثابتة في الكلاسات.

4.3 Native DTO Standards
DTOs ستكون final readonly قدر الإمكان، مع Types واضحة.

final readonly class StoreOrderData
{
    public function __construct(
        public string $reference,
        public int $merchantId,
        public array $items,
    ) {}

    public static function from(
        StoreOrderRequest $request
    ): self {
        $validated = $request->validated();

        return new self(
            reference: $validated['reference'],
            merchantId: (int) $validated['merchant_id'],
            items: $validated['items'],
        );
    }
}

قواعد DTO:

لا يحتوي Database Queries.

لا يحتوي HTTP Response.

لا يحتوي Business Side Effects.

يكون typed قدر الإمكان.

لا نحول كل Array إلى DTO بدون فائدة.

نستخدم Nested DTO عندما تكون البيانات المركبة ذات معنى مستقل.

نستخدم DTO للـ Input والـ Output عندما يكون ذلك مفيدًا لعزل العقود.

بالنسبة للـ Items، يمكن لاحقًا إنشاء OrderItemData بدل Array عامة، خصوصًا إذا أصبحت لها قواعد أو عمليات متكررة.

4.4 Action vs Service vs Query
ده قرار مهم جدًا لأن كثرة الـ Classes ممكن تضر بنفس قدر تكديس الكود.

النوع

نستخدمه عندما

مثال

Action

عملية Business محددة

CreateOrderAction

Service

سلوك مشترك أو عملية مركبة ذات مسؤولية مستقرة

OrderPricingService

Query

قراءة معقدة أو reusable أو ذات أداء حساس

SearchOrdersQuery

Job

تنفيذ غير متزامن

SyncCarrierOrdersJob

Event

الإعلان عن حدوث شيء

OrderCreated

القاعدة: Action تمثل Use Case، Service تمثل capability، Query تمثل قراءة.

لا نحتاج Action لكل عملية update() بسيطة، ولا Service واحدة ضخمة لكل Module.

مثال Action:

final class CreateOrderAction
{
    public function __construct(
        private OrderPricingService $pricing,
    ) {}

    public function handle(
        StoreOrderData $data
    ): Order {
        return DB::transaction(function () use ($data) {
            $order = Order::query()->create([
                'reference' => $data->reference,
                'merchant_id' => $data->merchantId,
            ]);

            foreach ($data->items as $item) {
                $order->items()->create([
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $order;
        });
    }
}

ده مثال توضيحي. في النسخة الإنتاجية، نضيف التحقق من ملكية الـ Merchant، واحتساب الأسعار، والقيود الفريدة، وسياسات التكرار حسب الـ Use Case.

4.5 Model Standards
Model مسؤول عن:

Eloquent relations.

Casts.

Scopes.

Attributes.

Persistence-related behavior.

قواعد بسيطة مرتبطة بكيان البيانات.

لا نضع فيه:

إرسال Emails.

استدعاء Carrier APIs.

إدارة Workflow كبير.

منطق تنفيذ Use Case كامل.

نستخدم:

Route Model Binding.

Eloquent Scopes.

Relationships.

Casts.

Policies.

Observers عند الحاجة الفعلية، وليس لإخفاء عمليات يصعب تتبعها.

4.6 API Resource Standards
كل API output يمر من Resource أو Resource Collection.

final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

قواعد:

لا نرجع Model مباشرة من API.

لا نستخدم toArray() في Controller لتجهيز JSON يدويًا.

لا نعمل N+1؛ العلاقات المطلوبة تكون eager-loaded من Query أو Action.

نستخدم whenLoaded() للعلاقات الاختيارية.

نستخدم Pagination الأصلية من Laravel.

4.7 Route Standards
Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::apiResource('orders', OrderController::class)
            ->only(['index', 'show', 'store']);

        Route::post(
            'orders/{order}/cancel',
            CancelOrderController::class
        )->name('orders.cancel');
    });

apiResource للـ CRUD API.

resource للـ Web CRUD عند الحاجة.

only() وexcept() لتحديد العمليات.

العمليات غير التقليدية لها Routes واضحة مثل cancel, confirm, dispatch.

API Versioning ليس مجرد Prefix؛ لازم نحافظ على التوافق مع العملاء ونختبر العقود.

5. Data Access, Performance & Security
5.1 Pagination
نختار نوع Pagination وفق استخدام الشاشة:

الاستخدام

الاختيار

جداول Admin مع أرقام صفحات

paginate()

صفحات لا تحتاج إجمالي النتائج

simplePaginate()

بيانات ضخمة وInfinite scrolling

cursorPaginate()

تقارير مجمعة

Query مخصصة مع حدود واضحة

لا نستخدم get() على جدول كبير لمجرد أنه أسهل.

5.2 Indexing
الـ Generator لا يستطيع معرفة كل Index صحيح تلقائيًا، لذلك يوفر إمكانية تعريف Indexes، لكن القرار النهائي يعتمد على Query patterns.

مثال:

$table->index(
    ['tenant_id', 'status', 'created_at'],
    'orders_tenant_status_created_index'
);

قبل إضافة Index:

نراجع الـ Queries الفعلية.

نستخدم EXPLAIN.

نراجع الـ Selectivity.

نراعي تكلفة الكتابة والتحديث.

نختبر تأثير الـ Index على البيانات الواقعية.

5.3 Multi-Tenancy
كل Module يتعامل مع Tenant context بطريقة موحدة.

المطلوب:

Tenant resolution في Middleware أو Context service.

عدم الثقة في tenant_id القادم من العميل.

Scoping للاستعلامات.

حماية العلاقات والـ Route Model Binding.

Unique constraints تشمل tenant عند الحاجة.

اختبارات تمنع قراءة أو تعديل بيانات Tenant آخر.

مثال قيد فريد:

$table->unique(
    ['tenant_id', 'reference'],
    'orders_tenant_reference_unique'
);

ولا نكتفي بـ Global Scope كطبقة حماية وحيدة؛ يجب أن تكون حدود الـ Tenant واضحة أيضًا في Actions وQueries والقيود على مستوى قاعدة البيانات.

5.4 Idempotency
مهم للعمليات الحساسة مثل إنشاء Order أو إنشاء Shipment أو إرسال طلب إلى Carrier.

التصميم المقترح:

العميل يرسل Idempotency-Key.

المفتاح مربوط بالـ Tenant والـ User أو API Client.

نخزن Request fingerprint.

نستخدم Unique Constraint في Database.

نفس المفتاح ونفس البيانات يعيد نفس النتيجة وفق سياسة العملية.

نفس المفتاح مع بيانات مختلفة ينتج Conflict.

نتعامل مع حالات in-progress, completed, failed بشكل محدد.

العمليات الخارجية تحتاج حماية إضافية عند إعادة المحاولة.

تعطيل زر Submit في Frontend يحسن تجربة المستخدم، لكنه لا يحل التكرار من جهة Backend.

5.5 CSRF & Rate Limiting
Blade/Session-authenticated web forms تستخدم CSRF protection.

API token authentication لا تعتمد على CSRF بالطريقة نفسها؛ الحماية تتحدد حسب آلية المصادقة.

Rate Limiting يطبق على Login وSearch وPublic APIs والعمليات المكلفة.

لا نلغي Middleware افتراضيًا لتسهيل التطوير.

6. Mixed Frontend Standard
مش هنفرض تقنية واحدة على كل شاشة.

نوع الشاشة

التقنية الافتراضية

CRUD إداري عادي

Blade

Forms وDialogs بسيطة

Blade Components

تفاعلات Server-driven

Livewire

Dashboard تفاعلي مع بيانات كثيرة

Vue

Complex tables وdrag/drop

Vue

Mobile-friendly server pages

Blade أو Livewire

API consumed by external clients

API Resources

Frontend rules
Blade

Components قابلة لإعادة الاستخدام.

لا نضع Business Logic في Blade.

لا نكرر markup لنفس عناصر الواجهة.

نستخدم layouts وpartials بشكل منظم.

Livewire

مناسب للتفاعلات التي تحتاج Server-driven UI.

Validation باستخدام Laravel/Livewire.

لا نكرر Business Rules داخل Component.

نستدعي نفس Actions المستخدمة في HTTP controllers.

Vue

مناسب للشاشات الغنية بالتفاعل.

يفصل API client عن UI components.

لا يكرر Business Rules الخاصة بالـ Backend.

يستخدم Pagination وSearch contracts موحدة.

Select2 AJAX Contract
أي Select2 أو searchable dropdown يجب أن يدعم:

q أو search.

page.

per_page بحد أقصى.

data وpagination.more.

Debouncing.

Server-side filtering.

Tenant scoping.

Authorization.

شكل الاستجابة المتفق عليه:

{
  "data": [
    {
      "id": 12,
      "text": "Product A"
    }
  ],
  "pagination": {
    "more": true
  }
}

الـ Generator سيقدر ينشئ Search endpoint عند استخدام خيار --searchable-select أو تعريف علاقة قابلة للبحث.

7. Advanced CRUD Generator
ده أهم جزء في المشروع؛ لأنه هيحوّل الـ Standard من مستند نظري إلى نظام قابل للتطبيق بشكل متكرر.

هنبني Artisan Generator داخلي اسمه:

php artisan make:module
php artisan make:module-crud

مش هنبدأ بكتابة Generator ضخم يعرف كل شيء. هنقسمه إلى Generator Engine + Definitions + Profiles + Stubs.

7.1 Generator architecture
app/
└── Shared/
    └── Generators/
        ├── Contracts/
        │   ├── Generator.php
        │   └── StubRenderer.php
        │
        ├── Commands/
        │   ├── MakeModuleCommand.php
        │   └── MakeModuleCrudCommand.php
        │
        ├── Definitions/
        │   ├── ModuleDefinition.php
        │   └── CrudDefinition.php
        │
        ├── Builders/
        │   ├── CrudBuilder.php
        │   └── ModuleBuilder.php
        │
        ├── Renderers/
        │   └── LaravelStubRenderer.php
        │
        ├── Writers/
        │   └── FileWriter.php
        │
        ├── Validators/
        │   └── GeneratorInputValidator.php
        │
        └── Support/
            ├── NameResolver.php
            ├── PathResolver.php
            └── StubResolver.php

stubs/
└── modules/
    ├── module/
    ├── crud/
    ├── nested-crud/
    └── profiles/
الـ Generator نفسه لا يعرف تفاصيل Business Logic الخاصة بالـ Orders أو Products. هو يعرف كيف يحول Definition إلى ملفات متوافقة مع الـ Standard.

7.2 Command design
إنشاء Module:

php artisan make:module Inventory

إنشاء CRUD أساسي:

php artisan make:module-crud Inventory Product

CRUD متقدم:

php artisan make:module-crud Inventory Product \
    --api \
    --web \
    --model \
    --migration \
    --factory \
    --policy \
    --search \
    --pagination \
    --tests

Nested CRUD:

php artisan make:module-crud Orders OrderItem \
    --parent=Order \
    --api \
    --tests

CRUD بخصائص إضافية:

php artisan make:module-crud Inventory Product \
    --profile=catalog \
    --search \
    --filters \
    --soft-deletes \
    --export

7.3 CRUD Profiles
بدلًا من عشرات الـ Flags في كل أمر، نعرّف Profiles جاهزة:

Profile

الاستخدام

الملفات المتوقعة

basic

بيانات بسيطة

Model, Migration, Resource, Request, Controller

catalog

Products, Categories

Search, Filters, Pagination, Policy

transactional

Orders, Shipments

Actions, DTOs, Transactions, Tests

master-data

Cities, Units, Types

CRUD بسيط وValidation

nested

Order Items, Bin Contents

Parent relation وScoped routes

workflow

Approvals, Status transitions

Actions, Policies, Events, Tests

الـ Profile يحدد Defaults فقط. Flags إضافية تقدر تضيف أو تعدل المخرجات.

7.4 Generator execution pipeline
1
Command Input

قراءة الاسم والـ Flags والـ Profile

2
Normalize

تحويل الأسماء إلى ModuleName وModelName وTableName

3
Validate

فحص التكرار والتعارض والـ Parent والـ Profile

4
Build Definition

تكوين Definition موحد للـ CRUD

5
Resolve Stubs

تحديد ملفات Stub المطلوبة فقط

6
Render

استبدال placeholders وإنتاج الملفات

7
Preview / Write

عرض الملفات أو كتابتها مع حماية الملفات الموجودة

8
Post Generation

عرض الملفات المنشأة وأوامر الاختبار التالية

7.5 Generator rules
لا يستبدل ملفًا موجودًا إلا بموافقة صريحة.

يدعم --dry-run لمعاينة الملفات قبل إنشائها.

يدعم --force فقط عندما يكون الاستبدال مقصودًا.

لا ينشئ ملفات فارغة أو Classes غير مستخدمة.

يراجع أسماء الملفات والـ Namespaces.

يحترم Module boundaries.

يدعم Nested resources وNested routes عند الحاجة.

يقدر يولد API فقط أو Web فقط أو الاثنين.

كل Profile له Tests تتأكد من الملفات التي ينتجها.

يدعم الإضافة التدريجية لـ Feature موجودة دون إعادة إنشاء الملفات القديمة.

7.6 Stub placeholders
مثال مبدئي:

<?php

namespace {{ namespace }}\Application\Actions;

use {{ namespace }}\Models\{{ model }};

final class {{ action }}Action
{
    public function handle(
        {{ dto }} $data
    ): {{ model }} {
        return {{ model }}::query()->create(
            $data->toArray()
        );
    }
}

لكن ده مجرد تصور للـ Stub، وليس النسخة النهائية؛ لأننا لازم نحدد شكل DTO النهائي، وعمليات الـ Model، والـ Tenant handling، وطريقة كتابة البيانات قبل اعتماد القالب.

7.7 Nested CRUD specification
الـ Nested CRUD يحتاج أكثر من إضافة parent_id.

مثال: Order يحتوي Order Items.

المطلوب:

Nested routes مثل orders/{order}/items.

Parent authorization.

التحقق من أن Item تابع فعلًا للـ Order المحدد.

عدم قبول Parent ID من المستخدم دون التحقق من السياق.

استخدام Relationship في الإنشاء.

Nested Resource عند الحاجة.

Tests لمحاولات الوصول إلى Child من Parent مختلف.

لا نستخدم Nested CRUD تلقائيًا لكل علاقة؛ بعض العلاقات تكون مستقلة وتحتاج Resource منفصلًا.

8. Testing Strategy
الاختبارات مش مرحلة أخيرة بعد بناء النظام. هي جزء من كل Generator Profile وكل Module.

8.1 Test layers
النوع

يختبر

مثال

Unit

منطق مستقل

Pricing calculation

Feature

HTTP behavior

Store Order endpoint

Integration

تكاملات حقيقية أو معزولة

Carrier client

Architecture

حدود الـ Modules

منع اعتماد Presentation على Module داخلي

Generator

صحة الملفات المنتجة

CRUD profile output

8.2 Feature test example
final class StoreOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_merchant_can_create_order(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->create();

        $user->givePermissionTo('orders.create');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/orders', [
                'reference' => 'ORD-1001',
                'merchant_id' => $merchant->id,
                'items' => [
                    [
                        'sku' => 'SKU-001',
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath(
                'data.reference',
                'ORD-1001'
            );

        $this->assertDatabaseHas('orders', [
            'reference' => 'ORD-1001',
            'merchant_id' => $merchant->id,
        ]);
    }
}

المثال يفترض وجود صلاحيات وعلاقات وRoute وResource متوافقة مع المشروع. في التنفيذ سنضبطه على نظام Authorization الفعلي بدل افتراض طريقة معينة.

8.3 Tests required لكل CRUD
Unauthorized request.

Invalid input.

Successful create.

Successful update.

Not found.

Tenant isolation.

Pagination.

Search and filters.

Soft delete / restore إن وجدت.

Nested ownership.

Duplicate submission للعمليات الحساسة.

API response contract.

8.4 Architecture tests
نحتاج اختبارات تمنع الانحراف المعماري:

Controller لا يحتوي استعلامات أو Business Logic.

Module لا يعتمد على Private internals من Module آخر.

لا يوجد Circular Dependency.

كل API Resource موجود في Presentation.

كل Action في Application.

لا توجد ملفات غير متوافقة مع Naming Convention.

مش لازم نكتب Static Analyzer من الصفر. نستخدم الأدوات الموجودة مثل PHPStan/Larastan وPest وLaravel Pint، ونضيف Architecture tests للقواعد التي لا تغطيها الأدوات الجاهزة.

9. Quality Gates & CI
كل Pull Request أو تغييرات رئيسية تمر عبر Quality Gates:

Required quality pipeline
Formatting

vendor/bin/pint --test

Static Analysis

vendor/bin/phpstan analyse

Architecture Tests

php artisan test --testsuite=Architecture

Feature & Unit Tests

php artisan test

Generator Tests

تشغيل الاختبارات على كل Profile وNested CRUD.

Build

npm run build

إعدادات PHPStan وPest وPint لازم تكون موحدة في المشروع. نبدأ بمستوى Static Analysis واقعي ثم نرفعه تدريجيًا بدل فرض مستوى عالٍ على قاعدة كود قديمة دفعة واحدة.

10. AI Coding Rules
دي نقطة أساسية لأن AI ممكن يكتب كود شكله صحيح لكنه يخالف الـ Architecture.

هننشئ ملف مركزي:

AI/
├── ENGINEERING_CONSTITUTION.md
├── ARCHITECTURE.md
├── CODING_STANDARDS.md
├── MODULE_RULES.md
├── TESTING_RULES.md
├── GENERATOR_RULES.md
├── SECURITY_RULES.md
└── WORKFLOWS/
    ├── create-module.md
    ├── create-crud.md
    ├── add-business-action.md
    ├── add-api-endpoint.md
    ├── add-integration.md
    └── review-code.md
10.1 Engineering Constitution
القواعد التي يجب أن يعرفها كل AI Agent:

اقرأ الملفات الحالية قبل كتابة أي كود.

لا تخترع Architecture جديدة.

لا تضع Business Logic داخل Controller أو FormRequest أو Blade.

استخدم Laravel native features قبل اقتراح Package.

استخدم الـ Generator عند إنشاء Module أو CRUD جديد.

لا تنشئ Abstract Class أو Interface دون سبب واضح.

لا تستخدم Repository Pattern تلقائيًا مع Eloquent.

لا تستخدم Trait لتجميع Business Logic.

لا تكرر Validation بين الطبقات بلا سبب.

كل تغيير له Tests مناسبة.

لا تعدّل ملفات خارج نطاق المهمة دون توضيح.

لا تعتبر المهمة مكتملة قبل تشغيل Quality Gates المتاحة.

10.2 AI Workflow
أي Feature جديدة تمر بالمراحل التالية:

AI implementation workflow
Understand — اقرأ Module والـ Routes والـ Models والـ Tests الحالية.

Plan — حدد الملفات والطبقات والاعتماديات المطلوبة.

Validate Architecture — تأكد أن التغيير لا يكسر حدود الـ Modules.

Generate — استخدم Generator لإنشاء الملفات القياسية.

Implement — أضف Business Logic في المكان الصحيح.

Test — اختبر الحالات الطبيعية والخاطئة والأمنية.

Review — راجع الأداء والـ Tenant isolation والـ Naming.

Report — اذكر ما تم تغييره والاختبارات التي نجحت وما لم يتم التحقق منه.

10.3 Definition of Done للـ AI
لا يعتبر AI الـ Feature منتهية إلا عندما:

كل الملفات في أماكنها الصحيحة.

الـ Routes مسجلة بالطريقة المتفق عليها.

Validation وAuthorization موجودان.

Business Logic خارج Controller.

لا يوجد Tenant data leakage.

لا يوجد N+1 واضح.

الاختبارات تغطي النجاح والفشل.

Pint وPHPStan والاختبارات تعمل أو يوضح AI سبب عدم تشغيلها.

لا توجد TODOs مخفية أو Mock implementations في Production code.

11. Laravel Engineering Standards: أدوات التنفيذ
الأدوات المقترحة:

الأداة

الاستخدام

Laravel Container

Dependency Injection

FormRequest

Validation + Authorization

Eloquent

Persistence وRelationships

Policies

Access control

API Resources

API serialization

Laravel Events

Event-driven communication

Queues / Jobs

Background processing

Laravel Cache

Caching

Laravel Rate Limiting

حماية endpoints

Laravel Notifications

Notifications

Laravel Pint

Formatting

PHPStan + Larastan

Static analysis

Pest

Testing

OpenAPI

API documentation

مش هنضيف Package لمجرد إن فيه Feature مشابهة. كل Package لازم يكون له سبب واضح، ويفضل أن يكون مدعومًا ومتوافقًا مع إصدار Laravel وPHP المستخدمين.

12. أمثلة الـ Standards حسب نوع الـ Feature
دي خريطة توضح إيه اللي يتولد وإيه اللي نضيفه حسب طبيعة العملية.

Feature

Controller

Request

DTO

Action

Query

Resource

List Products

نعم

Filter

اختياري

لا

نعم

نعم

Show Product

نعم

لا غالبًا

لا

لا

اختياري

نعم

Create Product

نعم

نعم

نعم

نعم

لا

نعم

Update Product

نعم

نعم

نعم

نعم

لا

نعم

Delete Product

نعم

نعم حسب السياسة

اختياري

نعم عند وجود قواعد

لا

حسب العقد

Search Products

نعم

نعم

اختياري

لا

نعم

نعم

Reserve Stock

نعم

نعم

نعم

نعم

حسب الحاجة

نعم

Import Products

نعم

نعم

نعم

نعم

نعم

حسب الحاجة

الفكرة: الـ CRUD Generator يولد الأساس، لكن العمليات المعقدة تتعامل معها كـ Use Cases مستقلة.

مثال: Reserve Stock
في نظام Inventory، حجز المخزون ليس مجرد update quantity.

يجب أن يدعم:

التأكد من صلاحية المخزون.

منع الحجز المكرر.

التعامل مع التزامن.

Database transaction.

منع تجاوز الكمية المتاحة.

تسجيل حركة المخزون.

عزل الـ Tenant.

Event أو Job بعد نجاح العملية حسب الحاجة.

ده مثال لعملية تحتاج Action واضحة، وربما Domain rules أو Service مستقلة، وليس مجرد CRUD عادي.

13. Implementation Roadmap
هننفذ المشروع على مراحل، وكل مرحلة لها مخرجات واختبارات وقبول واضح. مش هنبدأ بتوليد عشرات الملفات قبل تثبيت الأساس.

خطة التنفيذ
Phase 0 — Architecture Foundation

التصميم الحالي
اعتماد Folder Structure.

تعريف Module boundaries.

Naming conventions.

Dependency rules.

Architecture decision records.
Deliverable: Architecture specification نهائية.

Phase 1 — Core Infrastructure

Module registration.

Module Service Providers.

Route loading.

Autoloading.

Shared Kernel.

Tenant context integration.
Deliverable: Module يعمل داخل Laravel بدون CRUD.

Phase 2 — Coding Standards

Base Request conventions.

Native DTO patterns.

Actions وQueries.

Resources.

Exception handling.

Response contracts.
Deliverable: Reference Module يحتوي مثالًا كاملًا.

Phase 3 — Advanced Generator

Artisan Commands.

Definitions.

Stub rendering.

Profiles.

Nested CRUD.

Dry-run وoverwrite protection.
Deliverable: Generator يقدر ينشئ CRUD متكاملًا.

Phase 4 — Frontend Foundation

Blade component conventions.

Livewire conventions.

Vue integration.

AJAX Select2 contract.

Shared UI patterns.
Deliverable: Frontend architecture موحدة.

Phase 5 — Testing & Quality

Pest.

PHPStan/Larastan.

Pint.

Architecture tests.

Generator tests.

CI quality gates.
Deliverable: Automated engineering enforcement.

Phase 6 — AI Governance

Engineering Constitution.

Cursor rules.

AI workflows.

Code review rules.

Definition of Done.
Deliverable: AI agents تعمل داخل نفس المعايير.

Phase 7 — Pilot Module

تطبيق النظام على Module حقيقي.

مراجعة سهولة الاستخدام.

اختبار التوسع.

تحسين Generator.
Deliverable: Standard مثبت عمليًا قبل تعميمه.

14. Definition of Success
مش هنعتبر الـ Blueprint ناجح لمجرد أن الـ Folder Structure شكلها احترافي.

هنعتبره ناجح لما نقدر نعمل الآتي:

إنشاء Module جديد بأمر Artisan واحد.

إنشاء CRUD عادي بأمر واحد.

إنشاء Nested CRUD بدون نسخ ولصق.

إضافة Business Action بدون وضع منطق في Controller.

إنشاء API وWeb interfaces لنفس الـ Use Case.

كتابة Tests بشكل متوقع من الـ Generator.

إضافة Developer أو AI Agent جديد دون أن يخترع أسلوبًا مختلفًا.

اكتشاف المخالفات المعمارية آليًا قدر الإمكان.

الحفاظ على الأداء وأمان البيانات مع نمو النظام.

1. Module Registration
كل Module له Service Provider خاص به — أوصي بهذا.

2. Communication بين Modules

Public Contracts + Application Actions + Events — أوصي بهذا.

3. Multi-tenancy

دمج الـ Standard مع نظام Tenancy الحالي، 

4. Pilot Module
INventory — أفضل لاختبار المعاملات والمخزون


انا يهمني برضو الاسماء تكون بسيطه جدا جدا مباشره ابسط لغه لان انا عربي مصري الالنجليزي مش الالغه الاساسيه فاستخدام تسماء لفانكشن ومتيغرات معقده انجليزيهي بيسبب صعوبه ومش عاوزاسماء طويه قوي  
كمان هنستخدم باكجد multutenant https://tenancyforlaravel.com/ 
كمان مهم جدا معظم الفيتشرز تكون خاصه ليها on - off عشان لو هقفلها علي حد وهفاتحها لاخر وده هيكون في جدول اسمه features جوا كل tenant s as awewill suport for any app multi tenant mult database  وكل تينت هيكون فيها جدول فيتشر خاصه بيها ومحدش من صاحب اي نتيننت يقدر يعدل بنقرا نمها فقط  انا مك ادمن للممنصه هقدر بشكل ما اعمل لفيتشر on off والمفروظ تظهر بشكل مباشر 
وكمان اي سيتنج هتمكون من جوا في ستنج تيبل  key vlaue columsn بنقرا منه مفيش اي حاجهه هتتسل فالسينترال داات بيز الا الدومين والتتينيت واسم لداتابيز ال هوا لبيانات الاساسيه واي بدايه بتكون بكومند بنستخدمه فالسينترال عشان يعمل تيننت جديد ومش عاوز فاافستراكشر ال فوق نعمل ف كل موديول مايجريشن فولدر انا بفضل نستخدم الباث الطبيعي بتاع لارافيل واحد للسنيترال وواحد لل تيننت  وكون اليستم بيعتمد علي الساب دومني ف تمميز التينت 



تمام. التعديلات دي مهمة، وبتغيّر جزءًا أساسيًا من الـ Blueprint. والأهم إني متفق معاك في نقطة الأسماء: مش مطلوب إن الكود يبقى مليان مصطلحات إنجليزية معقدة عشان يكون Advanced. بالعكس، الكود الاحترافي لازم يكون سهل القراءة، خصوصًا للشخص اللي هيصونه ويطوره كل يوم.

هنضيف من دلوقتي قاعدة أساسية:

Simple English names. Short names. One clear meaning. No unnecessary technical vocabulary.

يعني نفضل:

CreateOrder
UpdateOrder
OrderData
OrderRequest
OrderResource
OrderService
OrderStatus

بدل أسماء طويلة أو مصطلحات من نوع:

ExecuteOrderCreationWorkflow
ProcessOrderMutationPayload
OrderPersistenceOrchestrationManager

ومش هنختصر الأسماء لدرجة إنها تبقى غامضة. مثلًا qty ممكن يكون مناسب في سياق واضح، لكن q أو proc أو hdl مش أسماء مقبولة.

هنطبق نفس القاعدة على:

أسماء Classes وMethods.

أسماء Variables وProperties.

أسماء Routes وDatabase columns.

أسماء Artisan Commands.

أسماء ملفات الـ Stubs.

أسماء الـ Features والـ Settings.

الهدف إنك تفتح أي ملف وتفهم دوره من أول نظرة، من غير ما تحتاج تترجم مصطلحات معقدة في دماغك.

1. تحديث الـ Multi-Tenancy Architecture
هنعتمد رسميًا على Tenancy for Laravel (stancl/tenancy)، وبالتحديد خط الإصدار 3.x حسب رابط التوثيق اللي بعته.

الـ Package يدعم:

قاعدة بيانات منفصلة لكل Tenant.

تحديد الـ Tenant من الـ Subdomain.

إنشاء قاعدة البيانات تلقائيًا.

تشغيل Migrations وSeeders لكل Tenant.

التحويل التلقائي لاتصال قاعدة البيانات عند دخول Tenant context. 

Tenancy for Laravel
+3

1.1 شكل النظام
Central Database
بيانات إدارة المنصة والتعريف بالـ Tenants فقط

tenants

domains

Tenant database name

Platform admin access عند الحاجة

Subdomain identification + database switching

Tenant A DB

tenant_a

features

settings

users

orders

inventory

Tenant B DB

tenant_b

features

settings

users

orders

inventory

كل Tenant له قاعدة مستقلة وجداول Features وSettings مستقلة.
مثال:

app.example.com       → Central
acme.example.com      → Tenant A
beta.example.com      → Tenant B
كل طلب على acme.example.com يحدد Tenant A، والـ Package يحوّل قاعدة البيانات تلقائيًا إلى قاعدة بياناته.

ملاحظة مهمة: في إصدار v3، المسار الرسمي المعتاد للـ Subdomain identification يعتمد على tenants وdomains وعلاقة الـ Domain بالـ Tenant. لذلك هنحتفظ بجداول الـ Package الأساسية بدل اختراع Resolver خاص من البداية. 

Tenancy for Laravel
+2

2. Database Structure
هنفصل بوضوح بين Central وTenant.

Central Database
Table

الاستخدام

tenants

تعريف كل Tenant

domains

الـ Subdomain أو Domain المرتبط به

جداول إدارة المنصة

حسابات وصلاحيات Platform Admin إذا احتجناها

الـ Tenant record يحتوي على:

id

اسم قاعدة البيانات الخاصة به، من خلال إعدادات قاعدة بيانات الـ Package أو عمود واضح مخصص.

بيانات التعريف الضرورية فقط.

مش هنحط فيها:

Products

Orders

Inventory

Users الخاصة بالـ Tenant

Features

Settings

أي Business Data خاصة بالعميل.

قاعدة: الـ Central Database هي دليل الوصول إلى الـ Tenant، مش مخزن بيانات تشغيله.

Tenant Database
كل قاعدة بيانات Tenant تحتوي على:

features
settings
users
roles
permissions

orders
order_items

products
categories

inventory
stock_movements

...
ومفيش مجلد Migrations لكل Module.

3. Features System — ON / OFF
دي هتبقى وظيفة أساسية في الـ Foundation، مش مجرد إعداد جانبي.

هنفصل بين ثلاثة أشياء:

Feature Definition: تعريف الميزة التي يدعمها النظام.

Feature State: هل الميزة مفعّلة لهذا الـ Tenant؟

Feature Access: هل المستخدم الحالي مسموح له باستخدامها؟

يعني تفعيل الميزة لا يعني أن كل مستخدم يقدر يستخدمها؛ الـ Permissions تفضل مسؤولة عن صلاحيات المستخدم.

3.1 جدول features داخل كل Tenant
features
--------
id
key
enabled
created_at
updated_at
مثال بيانات:

key

enabled

orders

1

inventory

1

barcode

0

reports

1

ai

0

exports

0

الـ key يكون ثابتًا، ويفضل Unique.

3.2 مين يقدر يعدل Features؟
Platform Admin

Read / Write
يقدر يفعّل أو يقفل أي Feature لأي Tenant من لوحة إدارة المنصة.
Tenant Owner

Read only
يقدر يشوف المميزات المتاحة والمفعّلة فقط، ولا يقدر يغيّر حالة أي Feature.
Tenant User

حسب الصلاحيات
يقدر يستخدم الميزة إذا كانت مفعّلة ولديه Permission مناسبة.
3.3 تفعيل Feature من لوحة المنصة
التدفق المقترح:

Platform Admin
      |
      v
Select Tenant
      |
      v
Select Feature
      |
      v
Turn ON / OFF
      |
      v
Enter Tenant Context
      |
      v
Update Tenant.features
      |
      v
Clear Feature Cache
      |
      v
Feature State Updated
التحديث يتم داخل قاعدة بيانات الـ Tenant نفسه، باستخدام Tenant::run() أو الآلية المناسبة التي يوفرها الـ Package، وليس بكتابة جدول Features في Central.

التفعيل يظهر في الطلب التالي مباشرة بعد نجاح التحديث وإبطال الـ Cache. ولو عندنا Workers أو جلسات طويلة، هنحتاج نضمن وصول التغيير لها أيضًا.

3.4 Feature names
مش هنخلي اسم الميزة مكتوبًا في أماكن كثيرة.

هنعمل تعريف مركزي في الكود، مثل:

final class Features
{
    public const ORDERS = 'orders';
    public const INVENTORY = 'inventory';
    public const BARCODE = 'barcode';
    public const REPORTS = 'reports';
    public const AI = 'ai';
}

والاستخدام:

if (feature('inventory')) {
    // ...
}

أو الأفضل داخل كود Backend:

if (Feature::enabled(Features::INVENTORY)) {
    // ...
}

المهم أن الـ Tenant لا يقدر يرسل enabled=true من API أو يعدل جدول Features بنفسه. التعديل يمر فقط من مسار Platform Admin المصرح به.

3.5 إخفاء الـ Feature من النظام
الـ Feature لازم تتقفل في كل الأماكن، مش في Sidebar فقط:

Sidebar وNavigation.

Routes.

API endpoints.

Actions.

Background Jobs عند الحاجة.

Scheduled Tasks.

Dashboard widgets.

Buttons وForms.

إخفاء الزر مش حماية كافية. لازم يكون فيه Backend enforcement، وإلا المستخدم يقدر يستدعي الـ Endpoint مباشرة.

4. Settings System
أي Setting تخص الـ Tenant تكون في قاعدة بياناته، مش في Central Database.

جدول settings
settings
--------
id
key
value
created_at
updated_at
مثال:

key

value

app.name

Isnaad

app.language

ar

orders.auto_confirm

false

inventory.allow_negative

false

orders.default_status

pending

هنخلي value من نوع JSON أو نص قابل لتخزين القيم البسيطة والمركبة، مع تحويله لنوع البيانات الصحيح عند القراءة.

الاستخدام:

Setting::get('app.language');
Setting::get('orders.auto_confirm');

ولو احتجنا تعديل Setting:

Setting::set('orders.auto_confirm', true);

لكن مين يقدر يعدّلها يتحدد حسب نوع الإعداد. مش كل Settings بالضرورة متاحة للـ Tenant Owner.

قواعد Settings
قاعدة البيانات هي مصدر الحقيقة.

القراءة من جدول settings.

ممكن نستخدم Cache لتحسين الأداء.

أي تعديل يعمل Cache invalidation.

لا نضع Tenant Settings في .env.

لا نكرر نفس Setting في أكثر من مكان.

لا نستخدم config() كبديل لتخزين إعدادات العملاء المتغيرة.

5. Migration Structure — Laravel Native
هنلغي فكرة وجود Migration folder داخل كل Module.

هنستخدم مسارات Laravel الطبيعية:

database/
├── migrations/
│   ├── central/
│   │   └── ...
│   │
│   └── ...
│
├── migrations/tenant/
│   ├── 0001_create_users_table.php
│   ├── 0002_create_features_table.php
│   ├── 0003_create_settings_table.php
│   ├── 0004_create_products_table.php
│   ├── 0005_create_orders_table.php
│   └── ...
│
├── factories/
└── seeders/
    ├── DatabaseSeeder.php
    ├── CentralSeeder.php
    └── TenantSeeder.php
والتقسيم النهائي:

المكان

قاعدة البيانات

database/migrations/

Central

database/migrations/tenant/

Tenant

ممكن نضع ملفات Central في database/migrations/central لو احتجنا تنظيمًا إضافيًا، لكن المسار الافتراضي في Laravel هو database/migrations، وهنحافظ عليه بدل تغيير سلوك Laravel بلا داعٍ.

الـ Package يسمح بتحديد مسار Tenant migrations، والمسار الافتراضي في توثيق v3 هو database/migrations/tenant. 

Tenancy for Laravel
+1

أوامر التشغيل:

php artisan migrate
php artisan tenants:migrate

ملحوظة مهمة: كل Migration لها اسم Class فريد، حتى لو جدول Central وTenant لهما أسماء متشابهة. 

Tenancy for Laravel

6. Tenant Creation Command
مش هنسمح بإنشاء Tenant جديد من خلال CRUD عادي في لوحة الإدارة.

إنشاء Tenant عملية خاصة لها Command واضح يعمل في Central Context.

الأمر المقترح:

php artisan tenant:create acme \
    --domain=acme.example.com \
    --database=tenant_acme

خطوات التنفيذ
التأكد من صحة اسم الـ Tenant والـ Domain.

التأكد أن الـ Domain غير مستخدم.

إنشاء Tenant في Central Database.

ربط الـ Domain بالـ Tenant.

إنشاء قاعدة البيانات الخاصة به عن طريق Package.

تشغيل Tenant Migrations.

إنشاء البيانات الأساسية.

إنشاء Feature records الافتراضية.

إنشاء Settings الافتراضية.

التأكد من نجاح التهيئة.

إظهار النتيجة في Terminal.

هنستخدم الـ Events وJob Pipeline الخاصين بالـ Package لتنفيذ خطوات إنشاء قاعدة البيانات والـ Migrations بالترتيب الصحيح، بدل كتابة دورة حياة Tenancy جديدة. 

Tenancy for Laravel
+1

النتيجة المتوقعة
Tenant created successfully.

Tenant: acme
Domain: acme.example.com
Database: tenant_acme

Database: Created
Migrations: Completed
Features: Seeded
Settings: Seeded

Status: Ready
ولو حصل فشل في خطوة، لازم نحدد حالة الـ Tenant بوضوح، ونوفر طريقة لإعادة استكمال التهيئة دون إنشاء بيانات مكررة.

7. Folder Structure — النسخة المعدلة
هنحتفظ بالـ Modular Architecture، لكن من غير Migration folders داخل Modules.

app/
├── Models/
│   ├── Tenant.php
│   └── Domain.php
│
├── Modules/
│   ├── Orders/
│   │   ├── Actions/
│   │   ├── Data/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Models/
│   │   ├── Queries/
│   │   ├── Services/
│   │   ├── Policies/
│   │   ├── Jobs/
│   │   ├── Events/
│   │   ├── Controllers/
│   │   ├── routes/
│   │   └── Provider.php
│   │
│   ├── Inventory/
│   │   └── ...
│   │
│   └── Products/
│       └── ...
│
├── Shared/
│   ├── Features/
│   ├── Settings/
│   ├── Support/
│   └── Contracts/
│
├── Console/
│   └── Commands/
│       └── CreateTenant.php
│
└── Providers/
    ├── AppServiceProvider.php
    ├── ModuleServiceProvider.php
    └── TenancyServiceProvider.php

database/
├── migrations/
├── migrations/tenant/
├── factories/
└── seeders/

resources/
├── views/
├── js/
└── css/

routes/
├── web.php
├── api.php
└── tenant.php

stubs/
└── ...
دي نسخة أبسط في القراءة، والـ Folder نفسه يوضح نوع الملف؛ مش محتاجين نكرر Application/Presentation/Infrastructure/Domain داخل كل Module لو ده هيزود التعقيد على المشروع.

هنحتفظ بالفصل المنطقي بين الطبقات عن طريق المجلدات الواضحة، مش عن طريق كثرة المستويات.

8. Naming Standard — قاعدة إلزامية
دي إضافة أساسية للـ Engineering Constitution.

النوع

الاسم المعتمد

اسم نتجنبه

Class

CreateOrder

ExecuteOrderCreationWorkflow

Class

OrderData

OrderPayloadTransferObject

Variable

$order

$orderEntityInstance

Variable

$items

$orderItemsCollectionPayload

Method

create()

executeOrderCreationProcess()

Method

enabled()

isFeatureActivationStatusEnabled()

Table

features

tenant_feature_activation_records

Column

database

tenant_database_connection_identifier

Route

orders.cancel

orders.execute-cancellation-workflow

قواعد الأسماء
كلمة أو كلمتين غالبًا تكفي.

استخدم كلمات إنجليزية شائعة.

اسم الكلاس يشرح وظيفته مباشرة.

اسم المتغير يعبر عن البيانات الموجودة فيه.

ممنوع الاختصارات الغامضة.

ممنوع تكرار اسم المجلد داخل اسم الكلاس دون فائدة.

لا تستخدم Manager, Processor, Orchestrator, Handler لمجرد جعل الاسم يبدو متقدمًا.

استخدم اسمًا طويلًا فقط إذا كان ضروريًا لإزالة الغموض.

مثلًا داخل مجلد Actions:

Actions/
├── CreateOrder.php
├── CancelOrder.php
├── UpdateOrder.php
└── ShipOrder.php
مش محتاجين:

CreateOrderAction.php
CancelOrderAction.php
UpdateOrderAction.php
ShipOrderAction.php
لأن المجلد نفسه وضّح النوع بالفعل.

9. تعديل الـ CRUD Generator
الـ Generator لازم يلتزم بكل القرارات الجديدة.

الأوامر تكون قصيرة:

php artisan make:module Orders

php artisan make:crud Orders Order

php artisan make:crud Inventory Product --api --web

php artisan make:crud Orders Item --parent=Order

ويعرف تلقائيًا:

مكان ملفات الـ Module.

مكان Migration الصحيح.

هل الـ Migration مركزية أم خاصة بالـ Tenant.

أسماء Classes القصيرة.

الـ Namespace.

الـ Routes.

الـ Tests.

الـ Features المطلوبة لو الـ CRUD تابع لميزة اختيارية.

مثلًا لو Inventory Feature مقفولة عند Tenant معين، الـ Generator يضيف تعريف الميزة للـ Module، ويجعل الـ Endpoints محمية بـ Feature check.

10. القرارات التي أصبحت معتمدة
Modular Monolith

Approved
Native DTO

Approved
Advanced CRUD Generator

Approved
Mixed Frontend

Approved
stancl/tenancy v3

Approved
Subdomain identification

Approved
Separate database per tenant

Approved
Central + Tenant migrations

Approved
Tenant-owned features/settings tables

Approved
Platform-admin feature control

Approved
Simple English naming

Approved
الخطوة التالية: Phase 0
قبل كتابة الكود، هنثبت 4 مستندات أساسية:

ARCHITECTURE.md — شكل المشروع وحدود الـ Modules.

NAMING.md — قواعد الأسماء البسيطة.

TENANCY.md — Central/Tenant DB وstancl/tenancy.

FEATURES.md — نظام ON/OFF وSettings والصلاحيات.

وبعد اعتمادهم نبدأ Phase 1: تجهيز Laravel وTenancy وربط الـ Providers والـ Routes والـ Migrations وإنشاء أول Tenant من Command.
في نقطة واحدة أحب أثبتها كقاعدة: الـ Feature لها تعريف ثابت داخل الكود، لكن حالة تشغيلها محفوظة داخل قاعدة بيانات كل Tenant، والتغيير متاح فقط لإدارة المنصة. كده نقدر نتحكم في كل عميل بشكل مستقل من غير ما نعمل Deploy جديد كل مرة.

ده هيكون أساس كل الـ Modules والـ Generator والـ AI Rules بعد كده.

---

## Permissions (spatie/laravel-permission, optional)

- Policies check named permissions `<group>.<ability>` (`products.update`). Names and roles live in one definitions class, `app/Shared/Permissions/Permissions.php` (`CentralPermissions` for the central side in tenant-saas); `permissions:sync` writes them to the database.
- Deny by default: a permission no role holds is denied. A `super-admin` role (config `blueprint.super_admin`) passes every check through `Gate::before`.
- `make:crud --permissions` generates the policy and tests; `--owned` adds ownership: a record the user cannot view is always 404, `<group>.any` only widens permissions the user already holds.
- tenant-saas: tenant roles live in tenant databases, central roles in the central database; they never mix, and the permission cache is per tenant.
- Details: `docs/PERMISSIONS.md`.
