<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\{Attendance, Classification, Employee, EmployeeAdvance, Partner, Payroll, PayrollAdvance, Product, Unit, WorkShift};
use App\Services\Admin\{AttendanceService, HrSettings, PayrollService};
use App\Support\Admin\Decimal as D;
use Carbon\{Carbon, CarbonPeriod};
use Database\Seeders\ErpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeAttendanceTest extends TestCase
{
    use RefreshDatabase;
    private Admin $admin;
    private Employee $employee;
    private WorkShift $shift;
    private array $location = ['latitude' => '31.95', 'longitude' => '35.91', 'accuracy' => '10'];

    protected function setUp(): void
    {
        parent::setUp(); config(['app.key'=>'base64:'.base64_encode(random_bytes(32)), 'app.timezone'=>'Asia/Amman']);
        date_default_timezone_set('Asia/Amman'); $this->travelTo(Carbon::parse('2026-10-19 08:15:00', 'Asia/Amman'));
        $this->seed(ErpSeeder::class); $this->admin = Admin::factory()->create(['is_super'=>true]);
        HrSettings::save(array_replace(HrSettings::DEFAULTS, ['latitude'=>'31.95', 'longitude'=>'35.91', 'work_days'=>[1,2,3,4,5], 'overtime_hour_rate'=>'4']));
        $this->shift = WorkShift::create(['name_ar'=>'صباحية', 'name_en'=>'Day', 'starts_at'=>'08:00', 'ends_at'=>'16:00']);
        $this->employee = Employee::create(['code'=>'E1','name_ar'=>'أحمد','name_en'=>'Ahmad','hire_date'=>'2026-09-01', 'shift_id'=>$this->shift->id,
            'monthly_salary'=>'528', 'username'=>'ahmad', 'password'=>Hash::make('employee-test-password')]);
        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function decimal(string $expected, $actual): void { $this->assertSame(0, D::cmp($expected, (string) $actual)); }
    private function loginEmployee(): void
    {
        Auth::guard('admin')->logout();
        $this->post(route('employee.login.submit'), ['username'=>'ahmad','password'=>'employee-test-password'])->assertRedirect(route('employee.dashboard'))->assertSessionHasNoErrors();
    }
    private function attend(string $date, string $in = '08:00', string $out = '16:00'): Attendance
    {
        $data = app(AttendanceService::class)->manual(['employee_id'=>$this->employee->id,'date'=>$date,'status'=>'present','check_in'=>$in,'check_out'=>$out], new Attendance());
        return Attendance::create($data);
    }
    private function september(): void
    {
        foreach (CarbonPeriod::create('2026-09-01','2026-09-30') as $day) { if ($day->isWeekday()) { $this->attend($day->toDateString()); } }
    }
    private function payroll(array $overrides = []): Payroll
    {
        $this->actingAs($this->admin,'admin')->post(route('admin.payroll.store'), array_replace(['employee_id'=>$this->employee->id,'month'=>'2026-09','allowance'=>'0','deduction'=>'0'], $overrides))->assertSessionHasNoErrors();
        return Payroll::where('employee_id',$this->employee->id)->where('month',$overrides['month'] ?? '2026-09')->firstOrFail();
    }
    private function advance(string $amount = '100', string $date = '2026-09-10'): EmployeeAdvance
    {
        return EmployeeAdvance::create(['employee_id'=>$this->employee->id,'date'=>$date,'amount'=>$amount,'request_id'=>(string) Str::uuid()]);
    }

    public function test_categories_are_scoped_and_customer_supplier_and_product_forms_have_no_codes(): void
    {
        foreach (['product_categories'=>'product','customer_categories'=>'customer','supplier_categories'=>'supplier'] as $resource=>$kind) {
            $this->post(route('admin.masters.store',$resource),['name_ar'=>$kind,'active'=>1])->assertSessionHasNoErrors();
            $this->assertTrue(Classification::where('kind',$kind)->exists());
        }
        foreach (['products','customers','suppliers'] as $resource) {
            $this->get(route('admin.masters.create',$resource))->assertOk()->assertDontSee('name="sku"',false)->assertDontSee('name="code"',false);
        }
        $customerCategory = Classification::where('kind','customer')->first();
        $this->post(route('admin.masters.store','customers'),['name_ar'=>'عميل جديد','classification_id'=>$customerCategory->id,'active'=>1])->assertSessionHasNoErrors();
        $partner = Partner::firstOrFail(); $this->assertNotEmpty($partner->code);
        $this->post(route('admin.masters.store','suppliers'),['name_ar'=>'Wrong kind','classification_id'=>$customerCategory->id,'active'=>1])->assertSessionHasErrors();
        $this->assertSame(1,Partner::count());
        $this->post(route('admin.masters.store','products'), ['name_ar'=>'صنف','classification_id'=>Classification::where('kind','product')->value('id'),
            'base_unit_id'=>Unit::first()->id,'cost'=>'0','selling_price'=>'2','minimum_stock'=>'0','active'=>1])->assertSessionHasNoErrors();
        $this->assertNotEmpty(Product::first()->sku);
        $this->get(route('admin.masters.index','customers').'?classification_id='.$customerCategory->id)->assertSee('عميل جديد');
    }

    public function test_employee_credentials_are_hashed_never_rendered_and_required_to_enable_login(): void
    {
        $data = ['code'=>'E2','name_ar'=>'موظف جديد','hire_date'=>'2026-09-01','monthly_salary'=>'400','shift_id'=>$this->shift->id,'active'=>1,'username'=>'new-employee'];
        $this->post(route('admin.masters.store','employees'),$data)->assertSessionHasErrors();
        $data += ['password'=>'secure-test-password','password_confirmation'=>'secure-test-password'];
        $this->post(route('admin.masters.store','employees'),$data)->assertSessionHasNoErrors();
        $employee = Employee::where('username','new-employee')->firstOrFail();
        $this->assertTrue(Hash::check($data['password'],$employee->password)); $this->assertArrayNotHasKey('password',$employee->toArray());
        $this->get(route('admin.masters.edit',['employees',$employee->id]))->assertOk()->assertDontSee($employee->password)->assertDontSee('value="secure-test-password"',false);
        $this->get(route('admin.masters.index','employees'))->assertDontSee($employee->password);
    }

    public function test_employee_login_is_separate_from_admin_and_other_employee_payslips(): void
    {
        $other = Employee::create(['code'=>'E2','name_ar'=>'خاص','name_en'=>'Private','hire_date'=>'2026-01-01']);
        $foreign = Payroll::create(['employee_id'=>$other->id,'month'=>'2026-09','salary'=>'9999','net'=>'9999']);
        $this->loginEmployee();
        $this->get(route('employee.dashboard'))->assertOk()->assertDontSee('9999');
        $this->get(route('employee.payslip',$foreign->id))->assertNotFound();
        $this->get(route('admin.hr.settings'))->assertRedirect(route('admin.showlogin'));
        $this->post(route('admin.hr.advances.store'),[])->assertRedirect(route('admin.showlogin'));
    }

    public function test_geofence_rejects_missing_outside_and_inaccurate_locations_without_writing(): void
    {
        $this->loginEmployee();
        $this->post(route('employee.attendance'),['action'=>'in'])->assertSessionHasErrors();
        $this->post(route('employee.attendance'),array_replace($this->location,['action'=>'in','latitude'=>'32.1']))->assertSessionHasErrors();
        $this->post(route('employee.attendance'),array_replace($this->location,['action'=>'in','accuracy'=>'150']))->assertSessionHasErrors();
        $this->assertSame(0,Attendance::count());
    }

    public function test_geofenced_punch_uses_server_time_and_employee_identity_and_retries_once(): void
    {
        $this->loginEmployee();
        for ($i=0;$i<2;$i++) { $this->post(route('employee.attendance'),$this->location+['action'=>'in','employee_id'=>999,'date'=>'1999-01-01','check_in'=>'01:00'])->assertSessionHasNoErrors(); }
        $row = Attendance::firstOrFail(); $this->assertSame(1,Attendance::count());
        $this->assertSame($this->employee->id,$row->employee_id); $this->assertSame('2026-10-19 08:15:00',$row->check_in_at->format('Y-m-d H:i:s'));
        $this->travelTo(Carbon::parse('2026-10-19 17:00:00','Asia/Amman'));
        $this->post(route('employee.attendance'),array_replace($this->location,['action'=>'out','longitude'=>'36.2']))->assertSessionHasErrors();
        $this->assertNull($row->fresh()->check_out_at);
        for ($i=0;$i<2;$i++) { $this->post(route('employee.attendance'),$this->location+['action'=>'out'])->assertSessionHasNoErrors(); }
        $this->assertSame('2026-10-19 17:00:00',$row->fresh()->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('employee',$row->fresh()->source);
    }

    public function test_disabled_accounts_and_password_changes_revoke_existing_sessions(): void
    {
        $this->loginEmployee(); $this->employee->update(['active'=>false]); Auth::forgetGuards();
        $this->get(route('employee.dashboard'))->assertRedirect(route('employee.login'));
        $this->post(route('employee.login.submit'),['username'=>'ahmad','password'=>'employee-test-password'])->assertSessionHasErrors();
        $this->employee->update(['active'=>true]); $this->loginEmployee();
        $this->employee->update(['password'=>Hash::make('a-different-password')]); Auth::forgetGuards();
        $this->post(route('employee.attendance'),$this->location+['action'=>'in'])->assertRedirect(route('employee.login'));
        $this->assertSame(0,Attendance::count());
    }

    public function test_overnight_shift_checkin_after_midnight_belongs_to_previous_work_day(): void
    {
        $this->shift->update(['starts_at'=>'22:00','ends_at'=>'06:00']);
        $this->travelTo(Carbon::parse('2026-10-20 00:15:00','Asia/Amman')); $this->loginEmployee();
        $this->post(route('employee.attendance'),$this->location+['action'=>'in'])->assertSessionHasNoErrors();
        $row = Attendance::firstOrFail(); $this->assertSame('2026-10-19',$row->date);
        $this->assertSame('2026-10-20 06:00:00',$row->scheduled_end->format('Y-m-d H:i:s'));
        $this->travelTo(Carbon::parse('2026-10-20 07:00:00','Asia/Amman'));
        $this->post(route('employee.attendance'),$this->location+['action'=>'out'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 07:00:00',$row->fresh()->check_out_at->format('Y-m-d H:i:s'));
    }

    public function test_salary_uses_scheduled_days_grace_excess_overtime_early_exit_absence_and_advances(): void
    {
        $this->september(); Attendance::where('date','2026-09-01')->delete(); $this->attend('2026-09-01','08:20','17:00');
        Attendance::where('date','2026-09-02')->delete(); Attendance::where('date','2026-09-03')->delete(); $this->attend('2026-09-03','08:00','15:30');
        $this->advance(); $payroll = $this->payroll(['allowance'=>'10','deduction'=>'2']);
        foreach (['salary'=>'528','absence_deduction'=>'24','late_deduction'=>'0.5','early_deduction'=>'1.5','overtime_pay'=>'4','advance_deduction'=>'100','net'=>'414'] as $field=>$value) { $this->decimal($value,$payroll->$field); }
        $this->assertSame(22,$payroll->details['scheduled_days']); $this->assertSame(10,$payroll->details['late_minutes']);
        $this->assertSame(60,$payroll->details['overtime_minutes']); $this->assertSame(1,$payroll->details['absent_days']);
        $this->get(route('admin.payroll.show',$payroll))->assertOk()->assertSee('414.000');
    }

    public function test_lateness_within_grace_is_free_and_fixed_hourly_deduction_is_respected(): void
    {
        $this->september(); Attendance::whereIn('date',['2026-09-01','2026-09-02'])->delete();
        $this->attend('2026-09-01','08:10'); $this->attend('2026-09-02','08:15');
        HrSettings::save(array_replace(HrSettings::get(),['late_hour_rate'=>'6']));
        $row = $this->payroll(); $this->decimal('0.5',$row->late_deduction); $this->decimal('527.5',$row->net);
        $this->assertSame(5,$row->details['late_minutes']);
    }

    public function test_incomplete_attendance_blocks_salary_and_future_month_is_rejected(): void
    {
        $this->attend('2026-09-01','08:00','');
        $this->post(route('admin.payroll.store'),['employee_id'=>$this->employee->id,'month'=>'2026-09','allowance'=>'0','deduction'=>'0'])->assertSessionHasErrors();
        $this->post(route('admin.payroll.store'),['employee_id'=>$this->employee->id,'month'=>'2026-11','allowance'=>'0','deduction'=>'0'])->assertSessionHasErrors();
        $this->assertSame(0,Payroll::count());
    }

    public function test_hire_date_prorates_by_scheduled_days_and_paid_leave_has_no_absence_deduction(): void
    {
        $this->employee->update(['hire_date'=>'2026-09-16']);
        foreach (CarbonPeriod::create('2026-09-16','2026-09-30') as $day) {
            if ($day->isWeekday()) { Attendance::create(['employee_id'=>$this->employee->id,'date'=>$day->toDateString(),'status'=>'leave']); }
        }
        $row=$this->payroll(); $this->assertSame(11,$row->details['eligible_days']);
        $this->decimal('264',$row->salary); $this->decimal('264',$row->net); $this->decimal('0',$row->absence_deduction);
    }

    public function test_month_end_overnight_shift_must_finish_before_payment(): void
    {
        $this->shift->update(['starts_at'=>'22:00','ends_at'=>'06:00']);
        $this->employee->update(['hire_date'=>'2026-09-30']);
        $this->travelTo(Carbon::parse('2026-10-01 02:00:00','Asia/Amman'));
        $row=$this->payroll(); $this->assertTrue($row->details['partial']);
        $this->post(route('admin.payroll.paid',$row))->assertSessionHasErrors();
        $this->assertNull($row->fresh()->paid_on);
        $this->travelTo(Carbon::parse('2026-10-01 06:30:00','Asia/Amman'));
        $this->attend('2026-09-30','22:00','06:00'); $row=$this->payroll();
        $this->assertFalse($row->details['partial']); $this->decimal('24',$row->net);
        $this->post(route('admin.payroll.paid',$row))->assertSessionHasNoErrors();
    }

    public function test_current_month_excludes_future_shifts_and_cannot_be_marked_paid(): void
    {
        $row = $this->payroll(['month'=>'2026-10']);
        $this->assertTrue($row->details['partial']); $this->assertSame('2026-10-16',$row->details['as_of']);
        $this->assertSame(12,$row->details['eligible_days']); $this->decimal('0',$row->net);
        $this->post(route('admin.payroll.paid',$row))->assertSessionHasErrors(); $this->assertNull($row->fresh()->paid_on);
    }

    public function test_advances_carry_forward_recalculate_once_and_release_on_unpaid_deletion(): void
    {
        $this->september(); $advance = $this->advance('600'); $row = $this->payroll();
        $this->decimal('528',$row->advance_deduction); $this->decimal('0',$row->net);
        $this->payroll(); $this->assertSame(1,Payroll::count()); $this->assertSame(1,PayrollAdvance::count());
        $this->decimal('528',PayrollAdvance::first()->amount);
        $this->delete(route('admin.hr.advances.destroy',$advance))->assertSessionHasErrors();
        $this->delete(route('admin.payroll.destroy',$row))->assertSessionHasNoErrors(); $this->assertSame(0,PayrollAdvance::count());
        $row = $this->payroll(); $this->post(route('admin.payroll.paid',$row))->assertSessionHasNoErrors();
        $this->decimal('72',D::sub($advance->amount, D::sum($advance->repayments()->pluck('amount'))));
        $this->travelTo(Carbon::parse('2026-11-02 10:00:00','Asia/Amman'));
        foreach (CarbonPeriod::create('2026-10-01','2026-10-31') as $day) { if ($day->isWeekday()) { $this->attend($day->toDateString()); } }
        $october = $this->payroll(['month'=>'2026-10']); $this->decimal('72',$october->advance_deduction); $this->decimal('456',$october->net);
        $this->decimal('600',D::sum(PayrollAdvance::pluck('amount')));
    }

    public function test_paid_payroll_and_its_attendance_are_locked_and_stale_unpaid_salary_requires_recalculation(): void
    {
        $this->september(); $row = $this->payroll(); $this->advance('50');
        $this->post(route('admin.payroll.paid',$row))->assertSessionHasErrors(); $this->assertNull($row->fresh()->paid_on);
        $row = $this->payroll(); $this->post(route('admin.payroll.paid',$row))->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.paid',$row))->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.store'),['employee_id'=>$this->employee->id,'month'=>'2026-09','allowance'=>'0','deduction'=>'0'])->assertSessionHasErrors();
        $attendance = Attendance::first();
        $this->put(route('admin.masters.update',['attendance',$attendance->id]),['employee_id'=>$this->employee->id,'date'=>$attendance->date,'status'=>'absent'])->assertSessionHasErrors();
        $this->assertSame('present',$attendance->fresh()->status);
        $this->delete(route('admin.payroll.destroy',$row))->assertSessionHasErrors();
    }

    public function test_advance_submission_is_idempotent_and_hr_controls_are_permission_protected(): void
    {
        $data = ['employee_id'=>$this->employee->id,'date'=>'2026-09-10','amount'=>'50','request_id'=>(string) Str::uuid()];
        for($i=0;$i<2;$i++) { $this->post(route('admin.hr.advances.store'),$data)->assertSessionHasNoErrors(); }
        $this->assertSame(1,EmployeeAdvance::count());
        $staff = Admin::factory()->create(); $staff->assignRole('erp_staff'); $this->actingAs($staff,'admin');
        $this->get(route('admin.hr.settings'))->assertForbidden(); $this->post(route('admin.hr.settings.save'),[])->assertForbidden();
        $this->get(route('admin.hr.advances'))->assertForbidden(); $this->post(route('admin.hr.advances.store'),$data)->assertForbidden();
    }

    public function test_settings_validate_coordinates_and_both_languages_render_all_new_screens(): void
    {
        $this->post(route('admin.hr.settings.save'),array_replace(HrSettings::get(),['latitude'=>91]))->assertSessionHasErrors();
        $this->post(route('admin.hr.settings.save'),array_replace(HrSettings::get(),['grace_minutes'=>5]))->assertSessionHasNoErrors();
        $this->assertSame(5,(int) HrSettings::get()['grace_minutes']); $this->advance(); $this->september(); $payroll=$this->payroll();
        foreach(['ar','en'] as $locale) {
            $this->post(route('admin.language',$locale));
            foreach(['admin.hr.settings','admin.hr.advances','employee.login'] as $route) {
                $response=$this->get(route($route))->assertOk(); $this->assertStringNotContainsString('erp.',strip_tags($response->getContent()));
                if (getenv('HR_CAPTURE')) { $this->capture($response->getContent(),str_replace('.','-',$route).'-'.$locale); }
            }
        }
        $this->loginEmployee();
        foreach(['ar','en'] as $locale) {
            $this->post(route('admin.language',$locale));
            foreach([route('employee.dashboard'),route('employee.payslip',$payroll)] as $url) {
                $response=$this->get($url)->assertOk(); $this->assertStringNotContainsString('erp.',strip_tags($response->getContent()));
                if (getenv('HR_CAPTURE')) { $this->capture($response->getContent(),(str_contains($url,'payslip') ? 'employee-payslip' : 'employee-dashboard').'-'.$locale); }
            }
        }
    }

    private function capture(string $html, string $name): void
    {
        $html = str_replace('http://localhost/assets','file:///'.str_replace('\\','/',base_path('assets')),$html);
        file_put_contents(storage_path('framework/'.$name.'.html'),$html);
    }
}
