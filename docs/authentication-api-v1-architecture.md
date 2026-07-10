# مستند نظام المصادقة والتحقق API V1

هذا المستند يشرح بالتفصيل العمل الذي تم على نظام Authentication و OTP في مشروع Laravel 12، مع توضيح كل ملف تم إنشاؤه أو تعديله، سبب القرار، السياسة المعمارية المتبعة، وطريقة التفكير النقدي التي تساعدك على بناء أنظمة احترافية بنفسك.

## 1. الهدف العام

الهدف كان بناء طبقة مصادقة احترافية تعتمد على:

- Laravel Sanctum لإصدار Personal Access Tokens.
- OTP عبر البريد الإلكتروني.
- فصل منطق الأعمال عن Controller.
- Services لكل مسؤولية واضحة.
- Config بدلاً من الأرقام الثابتة.
- FormRequest للتحقق من المدخلات.
- Notification بدلاً من `Mail::send`.
- API versioning تحت المسار `/api/v1`.

الفكرة الأساسية:

Controller لا يعرف كيف يتم التسجيل، ولا كيف يتم توليد الكود، ولا كيف يتم إرسال البريد. هو فقط يستقبل Request صالحاً، يستدعي Service، ويرجع ApiResponse.

## 2. الصورة المعمارية العامة

تم تقسيم النظام إلى طبقات:

```text
HTTP Request
    |
    v
FormRequest
    |
    v
AuthController
    |
    v
AuthService
    |
    +--> VerificationCodeService
    |
    +--> VerificationNotificationService
             |
             v
        Laravel Notification
```

هذا التقسيم يحقق:

- Single Responsibility Principle: كل كلاس له سبب واحد واضح للتغيير.
- Dependency Injection: الخدمات يتم حقنها في constructor.
- Testability: يمكن اختبار كل خدمة بمعزل عن Controller.
- Maintainability: إضافة SMS أو WhatsApp لاحقاً لا تحتاج تعديل AuthService.
- Production readiness: التعامل مع الاستثناءات والمعاملات والتحقق المركزي.

## 3. المكتبة التي تمت إضافتها

### `laravel/sanctum`

تمت إضافة:

```json
"laravel/sanctum": "^4.3"
```

في `composer.json`.

النسخة المثبتة في `composer.lock`:

```text
laravel/sanctum v4.3.2
```

سبب الإضافة:

- المشروع يحتاج Personal Access Token بعد تسجيل الدخول.
- `User::createToken()` غير متاح إلا بعد تثبيت Sanctum وإضافة trait `HasApiTokens`.
- Sanctum مناسب لـ APIs البسيطة والمتوسطة ويعمل جيداً مع mobile apps وSPA وtoken based authentication.

الأوامر التي تم تنفيذها:

```bash
composer require laravel/sanctum
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
```

## 4. الملفات التي تم إنشاؤها

### 4.1 `app/Services/AuthService.php`

هذه الخدمة هي مركز عمليات المصادقة فقط.

مسؤولياتها:

- تسجيل مستخدم جديد.
- تسجيل الدخول وإصدار Sanctum token.
- تسجيل الخروج وإلغاء token الحالي.
- طلب كود إعادة تعيين كلمة المرور.
- إعادة تعيين كلمة المرور بعد التحقق من OTP.
- تفعيل البريد الإلكتروني.
- إعادة إرسال كود التحقق.

المهم هنا أنها لا تولد OTP بنفسها ولا ترسل البريد بنفسها. هي تنسق بين الخدمات فقط.

#### Dependencies

```php
public function __construct(
    private readonly VerificationCodeService $verificationCodes,
    private readonly VerificationNotificationService $verificationNotifications,
    private readonly DatabaseManager $database,
    private readonly Hasher $hasher,
    private readonly ConfigRepository $config,
    private readonly Translator $translator,
) {
}
```

سبب هذه الاختيارات:

- `VerificationCodeService`: إدارة الأكواد.
- `VerificationNotificationService`: إرسال الأكواد.
- `DatabaseManager`: تشغيل transactions بدون Facade.
- `Hasher`: فحص وتشفير كلمة المرور بدون Facade.
- `ConfigRepository`: قراءة اسم token من config.
- `Translator`: رسائل قابلة للترجمة.

#### `register()`

يقوم بـ:

1. إنشاء المستخدم داخل transaction.
2. إنشاء email verification code عبر `VerificationCodeService`.
3. إرسال الكود عبر `VerificationNotificationService`.
4. إرجاع user وبيانات التحقق.

قرار مهم:

إرسال البريد يحدث بعد إنشاء المستخدم والكود. لو فشل إرسال البريد، سيظهر الخطأ بدلاً من إعطاء المستخدم انطباعاً أن كل شيء تم بنجاح.

تفكير نقدي:

في أنظمة أكبر، يمكن إرسال الإشعار عبر queue بعد commit باستخدام event أو queued notification. هذا يقلل زمن الاستجابة ويمنع فشل request بسبب مشكلة مؤقتة في البريد، لكنه يحتاج سياسة واضحة للتعامل مع فشل الإرسال.

#### `login()`

يقوم بـ:

1. البحث عن المستخدم بالبريد.
2. فحص كلمة المرور عبر `Hasher`.
3. إنشاء Sanctum token.
4. إرجاع `access_token` و `token_type`.

لماذا لا نستخدم `Auth::attempt()` هنا؟

لأننا نبني API token flow ونريد أن تبقى الخدمة مستقلة عن Facades والجلسات. استخدام `Hasher` مباشرة يجعل المنطق صريحاً وسهل الاختبار.

#### `logout()`

يقوم بحذف token الحالي فقط:

```php
$currentToken = $user->currentAccessToken();
```

ثم:

```php
$currentToken->delete();
```

السياسة:

تسجيل الخروج من الجهاز الحالي فقط، وليس حذف كل tokens الخاصة بالمستخدم. هذا أفضل لتجربة المستخدم في mobile apps لأن تسجيل الخروج من هاتف لا يجب أن يخرج المستخدم من كل الأجهزة إلا إذا كانت هذه سياسة مقصودة.

#### `forgotPassword()`

يقوم بإنشاء OTP من نوع `PASSWORD_RESET` وإرساله بالبريد.

قرار أمني مهم:

إذا كان البريد غير موجود، ترجع الخدمة:

```php
['sent' => true]
```

ولا ترمي خطأ.

السبب:

منع user enumeration. لا يجب أن يعرف المهاجم إن كان البريد مسجلاً أم لا من خلال endpoint استعادة كلمة المرور.

#### `resetPassword()`

يقوم بـ:

1. إيجاد المستخدم.
2. التحقق من OTP الخاص بإعادة التعيين.
3. تحديث كلمة المرور.
4. حذف كل tokens السابقة للمستخدم.

سبب حذف tokens:

بعد تغيير كلمة المرور، من الأفضل إلغاء الجلسات أو tokens القديمة لأن كلمة المرور قد تكون تغيرت بسبب اختراق أو نسيان. هذا قرار أمني محافظ.

#### `verifyEmail()`

يقوم بـ:

1. التحقق من OTP من نوع `EMAIL`.
2. تعبئة `email_verified_at`.
3. إرجاع المستخدم بعد refresh.

#### `resendVerificationCode()`

لا يقرر بنفسه هل يسمح بإعادة الإرسال. يستدعي:

```php
$this->verificationCodes->create($user, VerificationType::EMAIL);
```

والخدمة المختصة بالأكواد ترفض إنشاء كود جديد إذا يوجد كود صالح لم ينته.

هذه نقطة مهمة جداً في التصميم:

قاعدة منع إعادة الإرسال لا يجب أن تكون في AuthService فقط، لأنها قد تستخدم لاحقاً من Controller آخر أو Job أو Admin action. لذلك مكانها الصحيح هو VerificationCodeService.

### 4.2 `app/Services/VerificationCodeService.php`

هذه الخدمة مسؤولة فقط عن إدارة أكواد التحقق.

مسؤولياتها:

- إنشاء كود.
- التحقق من كود.
- جلب آخر كود.
- فحص وجود كود صالح.
- زيادة عدد المحاولات.
- تعليم الكود كمحقق.
- حذف الأكواد القديمة.
- حذف الأكواد المنتهية.
- توليد الكود داخلياً.

#### السياسة الأساسية للـ OTP

القواعد المطبقة:

- القناة الحالية: البريد الإلكتروني.
- طول الكود: من `config('verification.code_length')`.
- القيمة الافتراضية: 5 أرقام.
- التوليد: `random_int()`.
- الصلاحية: من `config('verification.expires_in_minutes')`.
- القيمة الافتراضية: 10 دقائق.
- الحد الأقصى للمحاولات: من `config('verification.max_attempts')`.
- القيمة الافتراضية: 5 محاولات.
- إذا يوجد كود صالح لنفس المستخدم ونفس النوع، يتم رفض إنشاء كود جديد.
- بعد انتهاء الكود يسمح بإنشاء كود جديد.
- عند إنشاء كود جديد يتم حذف الأكواد القديمة لنفس المستخدم والنوع.
- عند نجاح التحقق يتم تعبئة `verified_at`.

#### `create()`

يعمل داخل transaction:

```php
return $this->database->transaction(function () use ($user, $type): VerificationCode {
    ...
});
```

الخطوات:

1. يفحص وجود كود صالح.
2. إذا وجد كود صالح يرمي `ValidationException`.
3. يحذف الأكواد القديمة لنفس المستخدم والنوع.
4. ينشئ كوداً جديداً.

لماذا transaction؟

لأن فحص وجود كود ثم حذف الأكواد القديمة ثم إنشاء كود جديد عمليات مترابطة. لا نريد أن يحدث race condition بسيط ينتج عنه أكثر من كود فعال.

تفكير نقدي:

في الأنظمة ذات الضغط العالي جداً، transaction وحدها قد لا تكفي إذا حدث طلبان متزامنان ولا يوجد صف موجود لقفله. يمكن تطوير ذلك لاحقاً عبر:

- DB-level unique strategy.
- distributed lock.
- rate limiter.
- atomic cache lock.

لكن للتطبيق الحالي، هذا تصميم جيد ومناسب.

#### `verify()`

يعمل داخل transaction أيضاً.

الخطوات:

1. يجلب آخر كود غير محقق.
2. يستخدم `lockForUpdate()`.
3. يفحص وجود الكود.
4. يفحص انتهاء الصلاحية.
5. يفحص عدد المحاولات.
6. يقارن الكود عبر `hash_equals()`.
7. إذا كان خطأ، يزيد attempts ويرمي exception.
8. إذا كان صحيحاً، يملأ `verified_at`.

لماذا `hash_equals()`؟

لأنها أفضل من مقارنة عادية عند التعامل مع قيم حساسة. في OTP الرقمي القصير التأثير محدود، لكن استخدام نمط آمن يبني عادة صحيحة.

#### `generate()`

الدالة private كما طلبت.

```php
private function generate(): string
```

تستخدم:

```php
random_int(0, 9)
```

لكل رقم.

لماذا لم نستخدم:

```php
random_int(10000, 99999)
```

لأن ذلك لا يسمح بكود يبدأ بصفر. أما التوليد رقم برقم فيحافظ على إمكانية الأكواد مثل `01234`.

#### `deleteExpired()`

تسمح بحذف الأكواد المنتهية عامة أو لمستخدم ونوع معين.

هذه مفيدة لاحقاً في Scheduled Command مثل:

```php
$schedule->call(fn () => app(VerificationCodeService::class)->deleteExpired())->hourly();
```

لم أضف Scheduler الآن لأن الطلب كان حول نظام المصادقة و API routes، لكن الخدمة جاهزة له.

### 4.3 `app/Services/VerificationNotificationService.php`

هذه الخدمة مسؤولة فقط عن إرسال كود التحقق.

لا تنشئ الكود.
لا تحفظ الكود.
لا تتحقق من الكود.

هذا تطبيق صريح لـ Single Responsibility Principle.

#### `send()`

تحدد القناة المناسبة حسب نوع الكود.

حالياً الأنواع التي ترسل بالبريد:

- `EMAIL`
- `PASSWORD_RESET`
- `CHANGE_EMAIL`

إذا ظهر نوع لا تدعمه الخدمة، ترمي:

```php
InvalidArgumentException
```

#### لماذا هذه الخدمة مهمة؟

لأن AuthService لا يجب أن يعرف هل الإرسال عبر email أو SMS أو WhatsApp.

عند إضافة SMS لاحقاً:

- تضيف `sendSms()`.
- تعدل routing الداخلي داخل VerificationNotificationService.
- لا تعدل AuthService.

هذه هي قيمة Open Closed Principle:

نوسع السلوك بدون كسر الخدمات الأعلى.

### 4.4 `app/Notifications/VerificationCodeNotification.php`

Laravel Notification مسؤولة عن بناء رسالة البريد.

تستخدم:

```php
via(): array
```

وترجع:

```php
return ['mail'];
```

ثم:

```php
toMail()
```

يبني الرسالة.

لماذا Notification وليس `Mail::send()`؟

- Notification قابلة للتوسعة لقنوات متعددة.
- يمكن تحويلها إلى Queue لاحقاً بسهولة.
- تتكامل مع Notifiable داخل User.
- أنظف من وضع منطق البريد داخل Service أو Controller.

### 4.5 `app/Http/Responses/ApiResponse.php`

تمت إضافته لأن AuthController كان يستخدم `ApiResponse` لكن الكلاس لم يكن موجوداً.

مسؤولياته:

- توحيد شكل JSON response.
- تقليل تكرار `response()->json()`.
- جعل Controller أنحف.

الشكل الموحد للنجاح:

```json
{
    "success": true,
    "message": "...",
    "data": {}
}
```

والخطأ:

```json
{
    "success": false,
    "message": "...",
    "errors": {}
}
```

تفكير نقدي:

هذه خطوة جيدة، لكن في مشروع أكبر يمكن تطويرها إلى:

- API Resources.
- Exception rendering موحد.
- response macros.
- JSON:API standard.

### 4.6 `app/Http/Requests/Auth/ForgotPasswordRequest.php`

يتحقق من:

```php
'email' => ['required', 'email']
```

المهم:

التحقق من المدخلات في FormRequest وليس في Controller أو Service.

### 4.7 `app/Http/Requests/Auth/LogoutRequest.php`

يتحقق من أن هناك مستخدماً مصادقاً:

```php
return $this->user() instanceof User;
```

حتى logout لا يستقبل Request عادي، بل FormRequest كما طلبت.

### 4.8 `app/Http/Requests/Auth/ResendVerificationCodeRequest.php`

يتحقق من البريد فقط.

قرار مهم:

لا توجد قاعدة هنا تقول "اسمح أو ارفض إعادة الإرسال". هذه قاعدة Business، ومكانها VerificationCodeService.

FormRequest يهتم بصحة الشكل فقط، وليس سياسة النظام.

### 4.9 `app/Http/Requests/Auth/VerifyEmailRequest.php`

يتحقق من:

- email
- code

طول الكود يقرأ من:

```php
config('verification.code_length')
```

بدلاً من كتابة `digits:5` بشكل ثابت.

هذه نقطة احترافية:

إذا تغير طول الكود إلى 6 لاحقاً، لا تحتاج البحث في كل الملفات.

### 4.10 `routes/api_v1.php`

تم إنشاء ملف routes خاص بالإصدار الأول من الـ API.

المسارات:

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
POST /api/v1/auth/verify-email
POST /api/v1/auth/verification-code/resend
POST /api/v1/auth/logout
```

المسار المحمي:

```php
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', 'logout')->name('logout');
});
```

لماذا versioned API؟

لأنك ستحتاج مستقبلاً إلى تعديل شكل response أو contract بدون كسر التطبيقات القديمة.

مثلاً:

```text
/api/v1/auth/login
/api/v2/auth/login
```

هذا يسمح بالتطور دون إجبار كل clients على التحديث فوراً.

### 4.11 `database/migrations/2026_07_10_151917_create_personal_access_tokens_table.php`

تم إنشاؤه من Sanctum.

مسؤوليته إنشاء جدول:

```text
personal_access_tokens
```

هذا الجدول يخزن tokens المشفرة الخاصة بـ Sanctum.

حقول مهمة:

- `tokenable_type`
- `tokenable_id`
- `name`
- `token`
- `abilities`
- `last_used_at`
- `expires_at`

ملاحظة أمنية:

Sanctum لا يخزن plain token. يخزن hash. لذلك يجب إرجاع plain token للمستخدم مرة واحدة عند الإنشاء.

## 5. الملفات التي تم تعديلها

### 5.1 `app/Http/Controllers/AuthController.php`

كان Controller يحتوي على مشاكل:

- يستخدم `ApiResponse` بدون import واضح.
- يستخدم Requests غير مستوردة.
- يعتمد على `$this->authService` بدون constructor injection.
- فيه احتمال أن يتضخم منطق الأعمال لاحقاً.

بعد التعديل:

```php
public function __construct(
    private readonly AuthService $authService,
) {
}
```

كل action صار مختصراً:

```php
$result = $this->authService->login($request->validated());

return ApiResponse::success(
    $result,
    __('messages.login_success')
);
```

السياسة:

- Controller لا يستخدم Facades.
- Controller لا يحتوي Business Logic.
- Controller يستقبل FormRequest.
- Controller يرجع ApiResponse.

### 5.2 `app/Models/User.php`

تمت إضافة:

```php
use Laravel\Sanctum\HasApiTokens;
```

واستخدام:

```php
use HasApiTokens, HasFactory, Notifiable;
```

السبب:

حتى يصبح `createToken()` متاحاً على User.

كما تم تحسين relation:

```php
public function verificationCodes(): HasMany
```

إضافة return type تجعل الكود أوضح وأفضل للتحليل الساكن وقراءة المطورين.

### 5.3 `app/Models/VerificationCode.php`

تم تحسين:

- imports.
- return types.
- scopes.

مثلاً:

```php
public function user(): BelongsTo
```

و:

```php
public function scopeActive(Builder $query): Builder
```

و:

```php
public function scopeType(Builder $query, VerificationType $type): Builder
```

السبب:

Models ليست مكان Business Logic الثقيل، لكنها مكان مناسب للعلاقات وscopes البسيطة.

### 5.4 `app/Http/Requests/Auth/LoginUserRequest.php`

تمت إضافة PHPDoc وتحسين التوثيق.

المنطق نفسه بقي:

- email مطلوب وصحيح.
- password مطلوب string.

### 5.5 `app/Http/Requests/Auth/RegisterUserRequest.php`

تمت إضافة PHPDoc.

القواعد الموجودة بقيت مناسبة:

- name
- email unique
- phone unique
- birth_date
- password confirmed

### 5.6 `app/Http/Requests/Auth/ResetPasswordRequest.php`

تم تعديل قاعدة الكود من:

```php
'digits:5'
```

إلى:

```php
'digits:'.$this->codeLength()
```

والدالة:

```php
private function codeLength(): int
{
    return (int) config('verification.code_length');
}
```

السبب:

لا نريد أرقاماً ثابتة في validation تخالف config.

### 5.7 `config/verification.php`

كان الملف يحتوي قيماً ثابتة:

```php
'code_length' => 5,
'expires_in_minutes' => 10,
'max_attempts' => 5,
```

أصبح:

```php
'code_length' => (int) env('VERIFICATION_CODE_LENGTH', 5),
'expires_in_minutes' => (int) env('VERIFICATION_EXPIRES_IN_MINUTES', 10),
'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),
```

السبب:

في production قد تريد تغيير مدة OTP أو عدد المحاولات بدون تعديل الكود.

### 5.8 `config/auth.php`

تمت إضافة:

```php
'api_token_name' => env('AUTH_API_TOKEN_NAME', 'api-token'),
```

السبب:

اسم token لا يجب أن يكون hard-coded داخل AuthService.

### 5.9 `bootstrap/app.php`

تم ربط API routes:

```php
api: __DIR__.'/../routes/api_v1.php',
apiPrefix: 'api/v1',
```

وتم إضافة `SetLocale` إلى مجموعة API:

```php
$middleware->api(append: SetLocale::class);
```

السبب:

حتى رسائل الخطأ والنجاح في API تتبع `Accept-Language`.

كما تم التعامل مع guest redirects حتى لا يحاول API redirect إلى route باسم `login` غير موجود.

### 5.10 `lang/en/messages.php`

تمت إضافة رسائل:

- password reset success.
- code already sent.
- code not found.
- max attempts.
- email subject.
- password reset subject.
- email body lines.

السبب:

الخدمات ترمي exceptions ورسائل notification يجب أن تكون قابلة للترجمة.

### 5.11 `lang/ar/messages.php`

نفس الرسائل تمت إضافتها بالعربية.

الهدف:

تجربة API مفهومة للمستخدم العربي والإنجليزي.

### 5.12 `composer.json` و `composer.lock`

تم تحديثهما بسبب:

```bash
composer require laravel/sanctum
```

لا يجب تعديل `composer.lock` يدوياً. Composer هو الذي يديره.

## 6. سياسات النظام التي تم اعتمادها

### 6.1 Controller نحيف

السياسة:

Controller لا يحتوي قواعد العمل.

السبب:

عندما تضع business logic داخل Controller يصعب إعادة استخدامه واختباره. Controller يجب أن يكون adapter بين HTTP والعالم الداخلي للتطبيق.

### 6.2 FormRequest للتحقق من شكل المدخلات

FormRequest يجيب على سؤال:

هل شكل البيانات صحيح؟

ولا يجيب على سؤال:

هل مسموح تجارياً أو أمنياً تنفيذ العملية؟

مثال:

`ResendVerificationCodeRequest` يتأكد أن email صحيح، لكنه لا يقرر هل يوجد كود صالح. هذا القرار في `VerificationCodeService`.

### 6.3 Exceptions بدلاً من `return false`

في الحالات الحرجة مثل:

- كود خاطئ.
- كود منتهي.
- تجاوز المحاولات.
- مستخدم غير موجود في reset/verify.

نرمي `ValidationException`.

السبب:

`return false` يجعل caller ينسى التعامل مع الخطأ بسهولة. Exception تجبر flow أن يتوقف ويصل إلى طبقة Laravel exception handling.

### 6.4 Config بدلاً من hard-coded values

كل قيم OTP تقرأ من config:

- length.
- expiry.
- attempts.

هذا يجعل تغيير السياسة سهلاً.

### 6.5 استخدام Enums

تم استخدام:

```php
VerificationType::EMAIL
VerificationType::PASSWORD_RESET
```

بدلاً من strings عشوائية.

السبب:

Enums تمنع typo مثل:

```php
'passwrod_reset'
```

وتجعل IDE يفهم القيم الممكنة.

### 6.6 Transaction عند العمليات المركبة

استخدمنا transactions في:

- إنشاء كود تحقق.
- التحقق من كود.
- تسجيل مستخدم مع إنشاء OTP.
- reset password.
- verify email.

السبب:

أي عملية تتكون من أكثر من خطوة يجب أن تكون atomic قدر الإمكان.

### 6.7 منع إعادة إرسال OTP قبل انتهاء الصلاحية

السياسة:

إذا يوجد code صالح وغير منتهي لنفس المستخدم والنوع، نرفض إنشاء code جديد.

السبب:

- منع spam.
- تقليل الضغط على البريد.
- منع إرباك المستخدم بأكثر من كود.
- تقليل فرصة التخمين.

### 6.8 Forgot password لا يكشف وجود المستخدم

السياسة:

لو email غير موجود، نرجع response عام.

السبب:

حماية من user enumeration.

### 6.9 حذف tokens بعد reset password

السياسة:

بعد تغيير كلمة المرور، نحذف كل tokens القديمة للمستخدم.

السبب:

إذا كان هناك token مسروق، reset password يجب أن يقطع وصوله.

## 7. API Endpoints الحالية

### Register

```http
POST /api/v1/auth/register
```

Body:

```json
{
    "name": "Test User",
    "email": "user@example.com",
    "phone": "0500000000",
    "birth_date": "1995-01-01",
    "password": "password123",
    "password_confirmation": "password123"
}
```

ينشئ المستخدم ويرسل OTP للبريد.

### Login

```http
POST /api/v1/auth/login
```

Body:

```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

Response يحتوي:

```json
{
    "access_token": "...",
    "token_type": "Bearer"
}
```

### Logout

```http
POST /api/v1/auth/logout
```

Header:

```http
Authorization: Bearer {token}
```

محمي بـ:

```text
auth:sanctum
```

### Forgot Password

```http
POST /api/v1/auth/forgot-password
```

يرسل OTP من نوع `PASSWORD_RESET` إذا كان المستخدم موجوداً.

### Reset Password

```http
POST /api/v1/auth/reset-password
```

Body:

```json
{
    "email": "user@example.com",
    "code": "12345",
    "password": "new-password123",
    "password_confirmation": "new-password123"
}
```

### Verify Email

```http
POST /api/v1/auth/verify-email
```

Body:

```json
{
    "email": "user@example.com",
    "code": "12345"
}
```

### Resend Verification Code

```http
POST /api/v1/auth/verification-code/resend
```

يرفض إعادة الإرسال إذا يوجد كود صالح لم ينته.

## 8. كيف تفكر مثل Senior Laravel Architect

### السؤال الأول: من صاحب المسؤولية؟

عند كتابة أي كود اسأل:

هل هذا القرار HTTP؟

إذا نعم، مكانه Controller أو Request.

هل هذا القرار Business؟

إذا نعم، مكانه Service.

هل هذا القرار persistence بسيط؟

إذا نعم، قد يكون Model scope أو Repository في مشاريع أكبر.

هل هذا القرار delivery channel؟

إذا نعم، مكانه Notification service.

### السؤال الثاني: ماذا سيتغير مستقبلاً؟

في هذا النظام، المتوقع أن يتغير:

- طول الكود.
- مدة الصلاحية.
- عدد المحاولات.
- قناة الإرسال.
- نسخة API.
- شكل response.

لذلك جعلنا هذه النقاط قابلة للتغيير من أماكن واضحة.

### السؤال الثالث: أين الآثار الجانبية؟

الآثار الجانبية هنا:

- إنشاء user في قاعدة البيانات.
- إنشاء verification code.
- إرسال email.
- إنشاء token.
- حذف token.

كل أثر جانبي يجب أن يكون في مكان معروف وسهل الاختبار.

### السؤال الرابع: هل الخطأ يجب أن يكون قيمة أم Exception؟

الأخطاء التي تمثل فشل عملية حرجة يجب أن تكون Exception.

مثال:

- كود خاطئ.
- كود منتهي.
- محاولات كثيرة.

أما حالة forgot password مع email غير موجود فهي ليست خطأ ظاهراً للمستخدم لأسباب أمنية.

### السؤال الخامس: هل أستطيع اختبار هذا بدون HTTP؟

علامة التصميم الجيد:

يمكن اختبار `VerificationCodeService::verify()` بدون Controller.

يمكن اختبار `AuthService::login()` بدون route.

يمكن اختبار `VerificationNotificationService` باستخدام Notification fake.

## 9. نقاط تحتاج تطويراً لاحقاً

هذه ليست عيوباً تمنع النظام من العمل، لكنها خطوات احترافية لاحقة.

### 9.1 إضافة Feature Tests حقيقية

الاختبارات الحالية في المشروع هي الاختبارات الافتراضية.

ينبغي إضافة اختبارات لـ:

- register ينشئ user و verification code.
- login يرجع token.
- resend يرفض إذا يوجد code صالح.
- verifyEmail يملأ `email_verified_at`.
- resetPassword يغير password ويحذف tokens.
- max attempts تعمل كما يجب.
- expired code يرفض.

### 9.2 Rate Limiting

ينبغي إضافة rate limiting خاص بـ:

- login.
- forgot password.
- resend verification code.

السبب:

OTP system بدون rate limit يمكن أن يتعرض abuse حتى مع max attempts.

### 9.3 Queue للإشعارات

يمكن جعل `VerificationCodeNotification` implements `ShouldQueue`.

هذا يحسن response time لكنه يحتاج queue worker في production.

### 9.4 Indexes على verification_codes

لتحسين الأداء يمكن إضافة indexes مثل:

```text
user_id, type, expires_at, verified_at
```

خصوصاً إذا زاد عدد الأكواد.

### 9.5 Exception Response موحد

حالياً Laravel سيتعامل مع `ValidationException` بالطريقة الافتراضية.

يمكن لاحقاً تخصيص `bootstrap/app.php` داخل `withExceptions` لتوحيد كل أخطاء API بنفس شكل `ApiResponse`.

### 9.6 OpenAPI Documentation

بعد تثبيت API contracts، يمكن إنشاء Swagger/OpenAPI documentation.

هذا مهم عند وجود frontend أو mobile team.

## 10. أوامر التحقق التي تم تشغيلها

فحص syntax:

```bash
php -l ...
```

تنسيق الكود:

```bash
vendor/bin/pint --dirty
```

حل الخدمات من Laravel container:

```bash
php artisan tinker --execute='app(App\Services\AuthService::class); ...'
```

تشغيل الاختبارات:

```bash
php artisan test
```

النتيجة:

```text
2 tests passed
```

فحص routes:

```bash
php artisan route:list --path=api/v1
```

فحص route cache:

```bash
php artisan route:cache
php artisan route:clear
```

تم تشغيل migration الخاص بـ Sanctum بعد فتح XAMPP:

```bash
php artisan migrate
```

## 11. الخلاصة التعليمية

الفرق بين كود يعمل وكود احترافي ليس فقط أن endpoint يرجع response.

الفرق الحقيقي في الأسئلة:

- هل كل مسؤولية في مكانها؟
- هل يمكن تغيير السياسة بدون تفتيش المشروع كله؟
- هل Controller نحيف؟
- هل القواعد المهمة محمية من التكرار؟
- هل هناك transactions عند العمليات المركبة؟
- هل الأخطاء واضحة؟
- هل يستطيع مطور آخر فهم النظام بسرعة؟
- هل إضافة SMS لاحقاً ستكسر AuthService أم لا؟

النظام الحالي يضع أساساً جيداً:

- Services واضحة.
- Controller نحيف.
- OTP policies مركزية.
- Sanctum token flow جاهز.
- API versioning موجود.
- الإشعارات قابلة للتوسعة.
- config قابل للتغيير.

الخطوة التالية لتصبح أكثر احترافاً هي كتابة Feature Tests تغطي السلوك الحقيقي، ثم إضافة rate limiting و queue و API exception format موحد.
