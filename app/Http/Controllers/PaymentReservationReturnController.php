<?php
namespace App\Http\Controllers;

use Twilio\Rest\Client as Client_Twilio;
use App\Mail\PaymentReturn;
use App\Models\Client;
use App\Models\PaymentReservationReturn;
use App\Models\Role;
use App\Models\ReservationReturn;
use App\Models\Setting;
use App\utils\helpers;
use Carbon\Carbon;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\sms_gateway;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class PaymentReservationReturnController extends BaseController
{

    //------------- Get All Payment Reservation Returns --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_payments_Reservation_Returns', PaymentReservationReturn::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // Filter fields With Params to retriever
        $param = array(0 => 'like', 1 => '=', 2 => 'like');
        $columns = array(0 => 'ref', 1 => 'reservation_return_id', 2 => 'discount');
        $data = array();

        // Check If User Has Permission View  All Records
        $Payments = PaymentReservationReturn::with('ReservationReturn', 'ReservationReturn.client','account')
            ->where('deleted_at', '=', null)
            ->whereBetween('date', array($request->from, $request->to))
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })

        // Multiple Filter
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->whereHas('ReservationReturn.client', function ($q) use ($request) {
                        $q->where('id', '=', $request->client_id);
                    });
                });
            });
        $Filtred = $helpers->filter($Payments, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere('discount', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('ReservationReturn', function ($q) use ($request) {
                                $q->where('ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('ReservationReturn.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Payments = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Payments as $Payment) {

            $item['date']          = $Payment->date;
            $item['ref']           = $Payment->ref;
            $item['ref_return']    = $Payment['ReservationReturn']->ref;
            $item['client_name']   = $Payment['ReservationReturn']['client']->name;
            $item['discount']     = $Payment->discount;
            $item['amount']       = $Payment->amount;
            $item['account_name']  = $Payment['account']?$Payment['account']->account_name:'---';
            $data[] = $item;
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $reservation_returns = ReservationReturn::get(['ref', 'id']);

        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $data,
            'reservation_returns' => $reservation_returns,
            'clients' => $clients,
        ]);
    }

    //----------- Store New Payment Reservation Return --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', PaymentReservationReturn::class);
        
        if($request['amount'] > 0){
            \DB::transaction(function () use ($request) {
                $role = Auth::user()->roles()->first();
                $view_records = Role::findOrFail($role->id)->inRole('record_view');
                $ReservationReturn = ReservationReturn::findOrFail($request['reservation_return_id']);
        
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Reservation Return->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $ReservationReturn);
                }

                $total_paid = $ReservationReturn->paid_amount + $request['amount'];
                $due = $ReservationReturn->GrandTotal - $total_paid;

                if ($due === 0.0 || $due < 0.0) {
                    $payment_status = 'paid';
                } else if ($due !== $ReservationReturn->GrandTotal) {
                    $payment_status = 'partial';
                } else if ($due === $ReservationReturn->GrandTotal) {
                    $payment_status = 'unpaid';
                }

                PaymentReservationReturn::create([
                    'reservation_return_id' => $request['reservation_return_id'],
                    'account_id'     => $request['account_id']?$request['account_id']:NULL,
                    'ref' => $this->getNumberOrder(),
                    'date' => $request['date'],
                    'discount' => $request['discount'],
                    'amount' => $request['amount'],
                    'change' => $request['change'],
                    'notes' => $request['notes'],
                    'user_id' => Auth::user()->id,
                ]);

                $account = Account::where('id', $request['account_id'])->exists();

                if ($account) {
                    // Account exists, perform the update
                    $account = Account::find($request['account_id']);
                    $account->update([
                        'balance' => $account->balance - $request['amount'],
                    ]);
                }

                $ReservationReturn->update([
                    'paid_amount' => $total_paid,
                    'payment_status' => $payment_status,
                ]);

            }, 10);
        }

        return response()->json(['success' => true, 'message' => 'Payment Create successfully'], 200);
    }

    //------------ function show -----------\\

    public function show($id){
        //
        
        }

    //----------- Update Payment Reservation Return --------------\\

    public function update(Request $request, $id)
    {
       
        $this->authorizeForUser($request->user('api'), 'update', PaymentReservationReturn::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $payment = PaymentReservationReturn::findOrFail($id);
            
    
            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $ReservationReturn = ReservationReturn::find($payment->reservation_return_id);
            $old_total_paid = $ReservationReturn->paid_amount - $payment->amount;
            $new_total_paid = $old_total_paid + $request['amount'];
            $due = $ReservationReturn->GrandTotal - $new_total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_status = 'paid';
            } else if ($due !== $ReservationReturn->GrandTotal) {
                $payment_status = 'partial';
            } else if ($due === $ReservationReturn->GrandTotal) {
                $payment_status = 'unpaid';
            }

              //delete old balance
              $account = Account::where('id', $payment->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($payment->account_id);
                $account->update([
                    'balance' => $account->balance + $payment->amount,
                ]);
              }

            $payment->update([
                'date' => $request['date'],
                'account_id' => $request['account_id']?$request['account_id']:NULL,
                'discount' => $request['discount'],
                'amount' => $request['amount'],
                'change' => $request['change'],
                'notes' => $request['notes'],
            ]);

            //update new account
            $new_account = Account::where('id', $request['account_id'])->exists();

            if ($new_account) {
                // Account exists, perform the update
                $new_account = Account::find($request['account_id']);
                $new_account->update([
                    'balance' => $new_account->balance - $request['amount'],
                ]);
            }

    
            $ReservationReturn->update([
                'paid_amount' => $new_total_paid,
                'payment_status' => $payment_status,
            ]);
         
        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Update successfully'], 200);
    }

    //----------- Remove Payment Reservation Return --------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', PaymentReservationReturn::class);
        
        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $payment = PaymentReservationReturn::findOrFail($id);
    
            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $ReservationReturn = ReservationReturn::find($payment->reservation_return_id);
            $total_paid = $ReservationReturn->paid_amount - $payment->amount;
            $due = $ReservationReturn->GrandTotal - $total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_status = 'paid';
            } else if ($due !== $ReservationReturn->GrandTotal) {
                $payment_status = 'partial';
            } else if ($due === $ReservationReturn->GrandTotal) {
                $payment_status = 'unpaid';
            }

            PaymentReservationReturn::whereId($id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            $account = Account::where('id', $payment->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($payment->account_id);
                $account->update([
                    'balance' => $account->balance + $payment->amount,
                ]);
            }

            $ReservationReturn->update([
                'paid_amount' => $total_paid,
                'payment_status' => $payment_status,
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Delete successfully'], 200);

    }

    //----------- Number Order Payment Reservation Return --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('payment_reservation_returns')->latest('id')->first();

        if ($last) {
            $item = $last->ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'INV/RT_1111';
        }
        return $code;
    }

    //------------- Send Payment Reservation Return on Email -----------\\

    public function SendEmail(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentReservationReturn::class);

        $payment['id'] = $request->id;
        $payment['ref'] = $request->ref;
        $settings = Setting::where('deleted_at', '=', null)->first();
        $payment['company_name'] = $settings->CompanyName;
        
        $pdf = $this->payment_return($request, $payment['id']);
        $this->Set_config_mail(); // Set_config_mail => BaseController
        $mail = Mail::to($request->to)->send(new PaymentReturn($payment, $pdf));
        return $mail;
    }

    //----------- Payment Reservation Return PDF --------------\\

    public function payment_return(Request $request, $id)
    {
       
        $payment = PaymentReservationReturn::with('ReservationReturn', 'ReservationReturn.client')->findOrFail($id);

        $payment_data['return_ref'] = $payment['ReservationReturn']->ref;
        $payment_data['client_name'] = $payment['ReservationReturn']['client']->name;
        $payment_data['client_phone'] = $payment['ReservationReturn']['client']->phone;
        $payment_data['client_adr'] = $payment['ReservationReturn']['client']->adresse;
        $payment_data['client_email'] = $payment['ReservationReturn']['client']->email;
        $payment_data['amount'] = $payment->amount;
        $payment_data['ref'] = $payment->ref;
        $payment_data['date'] = $payment->date;
        $payment_data['discount'] = $payment->discount;

        $helpers = new helpers();
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.Payment_Reservation_Return', [
            'symbol'  => $symbol,
            'setting' => $settings,
            'payment' => $payment_data,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('Payment_Reservation_Return.pdf');


    }

     //-------------------Sms Notifications -----------------\\
     public function Send_SMS(Request $request)
     {
        $payment = PaymentReservationReturn::with('ReservationReturn', 'ReservationReturn.client')->findOrFail($request->id);
        $settings = Setting::where('deleted_at', '=', null)->first();
        $gateway = sms_gateway::where('id' , $settings->sms_gateway)
        ->where('deleted_at', '=', null)->first();

         $url = url('/api/payment_return_reservation_pdf/' . $request->id);
         $receiverNumber = $payment['ReservationReturn']['client']->phone;
         $message = "Dear" .' '.$payment['ReservationReturn']['client']->name." \n We are contacting you in regard to a Payment #".$payment['ReservationReturn']->ref.' '.$url.' '. "that has been created on your account. \n We look forward to conducting future business with you.";
         
          //twilio
        if($gateway->title == "twilio"){
            try {
    
                $account_sid = env("TWILIO_SID");
                $auth_token = env("TWILIO_TOKEN");
                $twilio_number = env("TWILIO_FROM");
    
                $client = new Client_Twilio($account_sid, $auth_token);
                $client->messages->create($receiverNumber, [
                    'from' => $twilio_number, 
                    'body' => $message]);
        
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

        }
        //nexmo
        // elseif($gateway->title == "nexmo"){
        //     try {

        //         $basic  = new \Nexmo\Client\Credentials\Basic(env("NEXMO_KEY"), env("NEXMO_SECRET"));
        //         $client = new \Nexmo\Client($basic);
        //         $nexmo_from = env("NEXMO_FROM");
        
        //         $message = $client->message()->send([
        //             'to' => $receiverNumber,
        //             'from' => $nexmo_from,
        //             'text' => $message
        //         ]);
                        
        //     } catch (Exception $e) {
        //         return response()->json(['message' => $e->getMessage()], 500);
        //     }
        // }
    }

}
