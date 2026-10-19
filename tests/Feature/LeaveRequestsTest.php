<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\{Attendance, Employee, LeaveBalance, LeaveRequest, Payroll, WorkShift};
use App\Services\Admin\{AttendanceService, HrSettings, LeaveService};
use App\Support\Admin\Decimal as D;
use Carbon\{Carbon, CarbonPeriod};
use Database\Seeders\ErpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveRequestsTest extends TestCase
{
    use RefreshDatabase;
    private Admin $admin;
    private Employee $employee;
    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp(); config(['app.key'=>'base64:'.base64_encode(random_bytes(32)), 'app.timezone'=>'Asia/Amman']);
        date_default_timezone_set('Asia/Amman'); $this->travelTo(Carbon::parse('2026-10-19 08:15:00','Asia/Amman'));
        $this->seed(ErpSeeder::class); $this->admin=Admin::factory()->create(['is_super'=>true,'password'=>Hash::make('old123')]);
        HrSettings::save(array_replace(HrSettings::DEFAULTS,['work_days'=>[1,2,3,4,5],'latitude'=>'31.95','longitude'=>'35.91']));
        $this->shift=WorkShift::create(['name_ar'=>'صباحي','name_en'=>'Day','starts_at'=>'08:00','ends_at'=>'16:00']);
        $this->employee=Employee::create(['code'=>'E1','name_ar'=>'موظف','name_en'=>'Employee','hire_date'=>'2026-01-01','monthly_salary'=>'528',
            'annual_leave_days'=>'14','shift_id'=>$this->shift->id,'username'=>'employee','password'=>Hash::make('emp123')]);
        $this->actingAs($this->admin,'admin');
    }
    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function decimal(string $expected, $actual): void { $this->assertSame(0,D::cmp($expected,(string)$actual)); }
    private function employeeSession(): void
    {
        Auth::guard('admin')->logout();
        $this->post(route('employee.login.submit'),['username'=>'employee','password'=>'emp123'])->assertSessionHasNoErrors()->assertRedirect(route('employee.dashboard'));
    }
    private function data(array $overrides=[]): array
    {
        return array_replace(['type'=>'annual','start_date'=>'2026-10-20','end_date'=>'2026-10-20','reason'=>'Family appointment','request_id'=>(string)Str::uuid()],$overrides);
    }
    private function requestLeave(array $overrides=[]): LeaveRequest
    {
        $this->employeeSession(); $this->post(route('employee.leaves.store'),$this->data($overrides))->assertSessionHasNoErrors();
        return LeaveRequest::latest('id')->firstOrFail();
    }
    private function review(LeaveRequest $leave,string $status='approved'): void
    {
        $this->actingAs($this->admin,'admin')->post(route('admin.hr.leaves.review',$leave),['status'=>$status,'review_note'=>'Reviewed'])->assertSessionHasNoErrors();
    }
    private function octoberAttendance(array $skip=[], array $times=[]): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 10:00:00','Asia/Amman'));
        foreach(CarbonPeriod::create('2026-10-01','2026-10-31') as $day) {
            $date=$day->toDateString(); if(!$day->isWeekday() || in_array($date,$skip)) continue;
            [$in,$out]=$times[$date] ?? ['08:00','16:00'];
            Attendance::create(app(AttendanceService::class)->manual(['employee_id'=>$this->employee->id,'date'=>$date,'status'=>'present','check_in'=>$in,'check_out'=>$out],new Attendance()));
        }
    }
    private function payroll(): Payroll
    {
        $this->actingAs($this->admin,'admin')->post(route('admin.payroll.store'),['employee_id'=>$this->employee->id,'month'=>'2026-10','allowance'=>'0','deduction'=>'0'])->assertSessionHasNoErrors();
        return Payroll::firstOrFail();
    }
    private function employeeData(array $overrides=[]): array
    {
        return array_replace(['code'=>$this->employee->code,'name_ar'=>'موظف','hire_date'=>'2026-01-01','monthly_salary'=>'528',
            'annual_leave_days'=>'14','shift_id'=>$this->shift->id,'username'=>'employee','active'=>1],$overrides);
    }

    public function test_six_character_passwords_work_for_admin_creation_update_and_self_profile(): void
    {
        $data=['name'=>'Operator','username'=>'six-admin','role'=>'erp_staff','password'=>'abc12','password_confirmation'=>'abc12'];
        $this->post(route('admin.access.store'),$data)->assertSessionHasErrors('password');
        $data['password']=$data['password_confirmation']='abc123';
        $this->post(route('admin.access.store'),$data)->assertSessionHasNoErrors();
        $operator=Admin::where('username','six-admin')->firstOrFail(); $this->assertTrue(Hash::check('abc123',$operator->password));
        $data['password']=$data['password_confirmation']='xyz123';
        $this->put(route('admin.access.update',$operator),$data)->assertSessionHasNoErrors(); $this->assertTrue(Hash::check('xyz123',$operator->fresh()->password));
        $profile=['username'=>$this->admin->username,'current_password'=>'old123','password'=>'new12','password_confirmation'=>'new12'];
        $this->post(route('admin.login.update',$this->admin->id),$profile)->assertSessionHasErrors('password');
        $profile['password']=$profile['password_confirmation']='new123';
        $this->post(route('admin.login.update',$this->admin->id),$profile)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new123',$this->admin->fresh()->password));
    }

    public function test_employee_password_minimum_and_confirmation_apply_to_creation_and_updates(): void
    {
        $data=$this->employeeData(['code'=>'E2','username'=>'newemployee','password'=>'abc12','password_confirmation'=>'abc12']);
        $this->post(route('admin.masters.store','employees'),$data)->assertSessionHasErrors('password');
        $data['password']='abc123'; $this->post(route('admin.masters.store','employees'),$data)->assertSessionHasErrors('password');
        $data['password_confirmation']='abc123'; $this->post(route('admin.masters.store','employees'),$data)->assertSessionHasNoErrors();
        $created=Employee::where('username','newemployee')->firstOrFail(); $this->assertTrue(Hash::check('abc123',$created->password));
        $this->put(route('admin.masters.update',['employees',$this->employee->id]),$this->employeeData(['password'=>'new123','password_confirmation'=>'new123']))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new123',$this->employee->fresh()->password));
    }

    public function test_cli_administrator_creation_accepts_six_characters_and_rejects_five(): void
    {
        foreach(['abc12'=>1,'abc123'=>0] as $password=>$exit) {
            $this->artisan('admin:create',['username'=>'cli-'.$exit])->expectsQuestion('Name','CLI Admin')->expectsQuestion('Email (optional)',null)
                ->expectsQuestion('Password (at least 6 characters)',$password)->expectsQuestion('Confirm password',$password)->assertExitCode($exit);
        }
        $this->assertFalse(Admin::where('username','cli-1')->exists()); $this->assertTrue(Hash::check('abc123',Admin::where('username','cli-0')->firstOrFail()->password));
    }

    public function test_request_counts_workdays_reserves_balance_and_retries_only_once(): void
    {
        $this->employeeSession(); $data=$this->data(['start_date'=>'2026-10-22','end_date'=>'2026-10-26']);
        for($i=0;$i<2;$i++) $this->post(route('employee.leaves.store'),$data)->assertSessionHasNoErrors();
        $this->assertSame(1,LeaveRequest::count()); $row=LeaveRequest::first(); $this->decimal('3',$row->charged_days);
        $balance=app(LeaveService::class)->balance($this->employee,2026); $this->decimal('3',$balance['reserved']); $this->decimal('11',$balance['available']);
        $this->review($row); $this->review($row);
        $balance=app(LeaveService::class)->balance($this->employee,2026); $this->decimal('3',$balance['used']); $this->decimal('0',$balance['reserved']); $this->decimal('11',$balance['available']);
    }

    public function test_insufficient_and_overlapping_requests_cannot_overdraw_balance(): void
    {
        $this->employee->update(['annual_leave_days'=>'1']); $this->requestLeave();
        $this->post(route('employee.leaves.store'),$this->data())->assertSessionHasErrors();
        $this->post(route('employee.leaves.store'),$this->data(['start_date'=>'2026-10-21','end_date'=>'2026-10-21']))->assertSessionHasErrors();
        $this->assertSame(1,LeaveRequest::count()); $this->decimal('0',app(LeaveService::class)->balance($this->employee,2026)['available']);
    }

    public function test_rejection_and_employee_cancellation_release_balance_without_double_refunds(): void
    {
        $row=$this->requestLeave(); $this->review($row,'rejected');
        $this->decimal('14',app(LeaveService::class)->balance($this->employee,2026)['available']);
        $row=$this->requestLeave();
        for($i=0;$i<2;$i++) $this->post(route('employee.leaves.cancel',$row->id))->assertSessionHasNoErrors();
        $this->decimal('14',app(LeaveService::class)->balance($this->employee,2026)['available']); $this->assertSame('cancelled',$row->fresh()->status);
        $this->actingAs($this->admin,'admin')->post(route('admin.hr.leaves.review',$row),['status'=>'approved'])->assertSessionHasErrors();
    }

    public function test_departure_is_fractional_and_approved_time_is_not_deducted_from_salary_twice(): void
    {
        $row=$this->requestLeave(['type'=>'departure','start_date'=>'2026-10-19','end_date'=>'2026-10-19','from_time'=>'14:00','to_time'=>'16:00']);
        $this->decimal('0.25',$row->charged_days); $this->review($row);
        $this->octoberAttendance([],['2026-10-19'=>['08:00','14:00']]); $payroll=$this->payroll();
        $this->decimal('0',$payroll->early_deduction); $this->decimal('528',$payroll->net); $this->assertSame(120,$payroll->details['approved_departure_minutes']);
        $this->decimal('13.75',app(LeaveService::class)->balance($this->employee,2026)['available']);
    }

    public function test_approved_annual_leave_is_paid_and_prevents_clocking_in_on_that_day(): void
    {
        $row=$this->requestLeave(); $this->review($row);
        $this->travelTo(Carbon::parse('2026-10-20 08:15:00','Asia/Amman')); $this->employeeSession();
        $this->post(route('employee.attendance'),['action'=>'in','latitude'=>'31.95','longitude'=>'35.91','accuracy'=>'10'])->assertSessionHasErrors();
        $this->assertSame(0,Attendance::count());
        $this->octoberAttendance(['2026-10-20']); $payroll=$this->payroll();
        $this->decimal('528',$payroll->net); $this->decimal('0',$payroll->absence_deduction); $this->assertSame(1,$payroll->details['leave_days']);
    }

    public function test_only_unapproved_lateness_is_deducted_and_admin_cancellation_restores_balance(): void
    {
        $row=$this->requestLeave(['type'=>'departure','start_date'=>'2026-10-19','end_date'=>'2026-10-19','from_time'=>'08:00','to_time'=>'10:00']);
        $this->review($row); $this->octoberAttendance([],['2026-10-19'=>['10:15','16:00']]);
        $payroll=$this->payroll(); $this->decimal('0.25',$payroll->late_deduction); $this->assertSame(5,$payroll->details['late_minutes']);
        $this->review($row,'cancelled'); $this->decimal('14',app(LeaveService::class)->balance($this->employee,2026)['available']);
        $payroll=$this->payroll(); $this->decimal('6.25',$payroll->late_deduction); $this->assertSame(125,$payroll->details['late_minutes']);
    }

    public function test_departure_credit_does_not_hide_unapproved_full_day_absence(): void
    {
        $row=$this->requestLeave(['type'=>'departure','from_time'=>'14:00','to_time'=>'16:00']);
        $this->review($row); $this->octoberAttendance(['2026-10-20']); $payroll=$this->payroll();
        $this->decimal('18',$payroll->absence_deduction); $this->decimal('510',$payroll->net);
        $this->assertSame(1,$payroll->details['absent_days']);
    }

    public function test_pending_requests_block_payment_and_approved_requests_lock_after_paid_payroll(): void
    {
        $row=$this->requestLeave(); $this->octoberAttendance(['2026-10-20']); $payroll=$this->payroll();
        $this->post(route('admin.payroll.paid',$payroll))->assertSessionHasErrors();
        $this->review($row); $this->post(route('admin.payroll.paid',$payroll))->assertSessionHasErrors();
        $payroll=$this->payroll(); $this->post(route('admin.payroll.paid',$payroll))->assertSessionHasNoErrors();
        $this->post(route('admin.hr.leaves.review',$row),['status'=>'cancelled'])->assertSessionHasErrors();
        $this->assertSame('approved',$row->fresh()->status);
    }

    public function test_employee_cannot_read_or_cancel_someone_elses_request_or_approve_own_request(): void
    {
        $row=$this->requestLeave();
        $other=Employee::create(['code'=>'E2','name_ar'=>'خاص','name_en'=>'Private','hire_date'=>'2026-01-01','username'=>'other','password'=>Hash::make('oth123'),'annual_leave_days'=>'14','shift_id'=>$this->shift->id]);
        $this->post(route('employee.login.submit'),['username'=>'other','password'=>'oth123'])->assertSessionHasNoErrors();
        $this->get(route('employee.leaves'))->assertOk()->assertDontSee('Family appointment');
        $this->post(route('employee.leaves.cancel',$row->id))->assertNotFound();
        $this->post(route('admin.hr.leaves.review',$row),['status'=>'approved'])->assertRedirect(route('admin.showlogin'));
        $this->assertSame('pending',$row->fresh()->status);
        $staff=Admin::factory()->create(); $staff->assignRole('erp_staff'); $this->actingAs($staff,'admin');
        $this->get(route('admin.hr.leaves'))->assertForbidden(); $this->post(route('admin.hr.leaves.review',$row),['status'=>'approved'])->assertForbidden();
    }

    public function test_allowance_updates_cannot_go_below_reserved_days_and_years_are_independent(): void
    {
        $this->requestLeave(); $this->actingAs($this->admin,'admin');
        $this->put(route('admin.masters.update',['employees',$this->employee->id]),$this->employeeData(['annual_leave_days'=>'0']))->assertSessionHasErrors();
        $this->decimal('14',$this->employee->fresh()->annual_leave_days);
        $this->put(route('admin.masters.update',['employees',$this->employee->id]),$this->employeeData(['annual_leave_days'=>'20']))->assertSessionHasNoErrors();
        $this->decimal('19',app(LeaveService::class)->balance($this->employee->fresh(),2026)['available']);
        $this->travelTo(Carbon::parse('2027-01-04 08:00:00','Asia/Amman'));
        $this->put(route('admin.masters.update',['employees',$this->employee->id]),$this->employeeData(['annual_leave_days'=>'10']))->assertSessionHasNoErrors();
        $this->decimal('20',LeaveBalance::where('year',2026)->value('allowance')); $this->decimal('10',LeaveBalance::where('year',2027)->value('allowance'));
    }

    public function test_invalid_date_ranges_nonworkdays_and_departures_outside_shift_are_rejected(): void
    {
        $this->employeeSession();
        foreach([
            ['start_date'=>'2026-10-18','end_date'=>'2026-10-18'],
            ['start_date'=>'2026-12-31','end_date'=>'2027-01-01'],
            ['start_date'=>'2026-10-24','end_date'=>'2026-10-25'],
            ['type'=>'departure','from_time'=>'07:00','to_time'=>'09:00'],
            ['type'=>'departure','from_time'=>'08:00','to_time'=>'16:00'],
            ['type'=>'departure','from_time'=>'15:00','to_time'=>'14:00'],
            ['type'=>'departure','from_time'=>'14:00','to_time'=>'15:00','end_date'=>'2026-10-21'],
        ] as $data) $this->post(route('employee.leaves.store'),$this->data($data))->assertSessionHasErrors();
        $this->assertSame(0,LeaveRequest::count());
    }

    public function test_overnight_departure_and_approval_attendance_conflict(): void
    {
        $this->shift->update(['starts_at'=>'22:00','ends_at'=>'06:00']);
        $row=$this->requestLeave(['type'=>'departure','from_time'=>'02:00','to_time'=>'04:00']);
        $this->decimal('0.25',$row->charged_days); $this->assertSame('2026-10-21 02:00:00',$row->days['2026-10-20']['start']);
        $row=$this->requestLeave(['start_date'=>'2026-10-21','end_date'=>'2026-10-21']);
        Attendance::create(['employee_id'=>$this->employee->id,'date'=>'2026-10-21','status'=>'present','check_in'=>'22:00']);
        $this->actingAs($this->admin,'admin')->post(route('admin.hr.leaves.review',$row),['status'=>'approved'])->assertSessionHasErrors();
        $this->assertSame('pending',$row->fresh()->status);
    }

    public function test_new_pages_render_in_both_languages_and_attendance_is_visible_to_hr(): void
    {
        $row=$this->requestLeave();
        $this->post(route('employee.attendance'),['action'=>'in','latitude'=>'31.95','longitude'=>'35.91','accuracy'=>'10'])->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-10-19 16:00:00','Asia/Amman'));
        $this->post(route('employee.attendance'),['action'=>'out','latitude'=>'31.95','longitude'=>'35.91','accuracy'=>'10'])->assertSessionHasNoErrors();
        foreach(['ar','en'] as $locale) {
            $this->post(route('admin.language',$locale));
            $response=$this->get(route('employee.leaves'))->assertOk(); $this->assertStringNotContainsString('erp.',strip_tags($response->getContent()));
            if(getenv('LEAVE_CAPTURE')) $this->capture($response->getContent(),'employee-leaves-'.$locale);
            $this->actingAs($this->admin,'admin');
            $this->get(route('admin.masters.index','attendance'))->assertOk()->assertSee('08:15:00')->assertSee('16:00:00');
            $response=$this->get(route('admin.hr.leaves',['employee_id'=>$this->employee->id]))->assertOk(); $this->assertStringNotContainsString('erp.',strip_tags($response->getContent()));
            if(getenv('LEAVE_CAPTURE')) $this->capture($response->getContent(),'admin-leaves-'.$locale);
        }
    }

    private function capture(string $html,string $name): void
    {
        file_put_contents(storage_path('framework/'.$name.'.html'),str_replace('http://localhost/assets','file:///'.str_replace('\\','/',base_path('assets')),$html));
    }
}
