<?php

namespace App\Http\Controllers;
use Twilio\Rest\Client as Client_Twilio;
use GuzzleHttp\Client as Client_guzzle;
use App\Models\SMSMessage;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Illuminate\Support\Str;
use App\Models\EmailMessage;
use App\Mail\CustomEmail;
use App\Models\Account;

use App\Mail\ReservationMail;
use App\Models\Client;
use App\Models\Unit;
use App\Models\PaymentReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\sms_gateway;
use App\Models\Role;
use App\Models\ReservationReturn;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Setting;
use App\Models\PosSetting;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Stripe;
use App\Models\PaymentWithCreditCard;
use DB;
use PDF;
use ArPHP\I18N\Arabic;
use App\Models\Post;
use App\Models\Service;

class ReservationsController extends BaseController
{

    //------------- GET ALL RESERVATIONS -----------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Reservation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $param = array(
            0 => 'like',
            1 => 'like',
            2 => '=',
            3 => 'like',
            4 => '=',
            5 => '=',
            6 => 'like',
        );
        $columns = array(
            0 => 'ref',
            1 => 'status',
            2 => 'client_id',
            3 => 'payment_status',
            4 => 'warehouse_id',
            5 => 'date',
            6 => 'shipping_status',
        );
        $data = array();

        // Check If User Has Permission View  All Records
        $Reservations = Reservation::with('facture', 'client', 'warehouse','user')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
        //Multiple Filter
        $Filtred = $helpers->filter($Reservations, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('status', 'LIKE', "%{$request->search}%")
                        ->orWhere('total_price', $request->search)
                        ->orWhere('payment_status', 'like', "%{$request->search}%")
                        ->orWhere('shipping_status', 'like', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        $Reservations = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Reservations as $Reservation) {
            
            $item['id'] = $Reservation['id'];
            $item['date'] = $Reservation['date'];
            $item['ref'] = $Reservation['ref'];
            $item['created_by'] = $Reservation['user']->username;
            $item['status'] = $Reservation['status'];
            $item['shipping_status'] =  $Reservation['shipping_status'];
            $item['discount'] = $Reservation['discount'];
            $item['shipping'] = $Reservation['shipping'];
            $item['warehouse_name'] = $Reservation['warehouse']['name'];
            $item['client_id'] = $Reservation['client']['id'];
            $item['client_name'] = $Reservation['client']['name'];
            $item['client_email'] = $Reservation['client']['email'];
            $item['client_tele'] = $Reservation['client']['phone'];
            $item['client_code'] = $Reservation['client']['code'];
            $item['client_adr'] = $Reservation['client']['adresse'];
            $item['total_price'] = number_format($Reservation['total_price'], 2, '.', '');
            $item['paid_amount'] = number_format($Reservation['paid_amount'], 2, '.', '');
            $item['due'] = number_format($item['total_price'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Reservation['payment_status'];

            if (ReservationReturn::where('reservation_id', $Reservation['id'])->where('deleted_at', '=', null)->exists()) {
                $sellReturn = ReservationReturn::where('reservation_id', $Reservation['id'])->where('deleted_at', '=', null)->first();
                $item['reservation_return_id'] = $sellReturn->id;
                $item['reservation_has_return'] = 'yes';
            }else{
                $item['reservation_has_return'] = 'no';
            }
            
            $data[] = $item;
        }
        
        $stripe_key = config('app.STRIPE_KEY');
        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);

       //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

        return response()->json([
            'stripe_key' => $stripe_key,
            'totalRows' => $totalRows,
            'reservations' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
        ]);
    }

    //------------- STORE NEW RESERVATION-----------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Reservation::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'post_id' => 'required|exists:posts,id',
            'service_id' => 'required|exists:services,id',
        ]);

        \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $order = new Reservation;

            $order->is_pos = 0;
            $order->date = $request->date;
            $order->ref = $this->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->total_price = $request->total_price;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->tax_net = $request->tax_net;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->status = $request->status;
            $order->payment_status = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;
            $order->post_id = $request->post_id;
            $order->service_id = $request->service_id;
            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {
                $unit = Unit::where('id', $value['reservation_unit_id'])
                    ->first();
                $reservationItem[] = [
                    'date'         => $request->date,
                    'reservation_id'      => $order->id,
                    'reservation_unit_id' => $value['reservation_unit_id']?$value['reservation_unit_id']:NULL,
                    'qte'     => $value['qte'],
                    'price'        => $value['price'],
                    'tax_net'       => $value['tax_percent'],
                    'tax_method'   => $value['tax_method'],
                    'discount'     => $value['discount'],
                    'discount_method'    => $value['discount_Method'],
                    'product_id'         => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id']?$value['product_variant_id']:NULL,
                    'total'              => $value['subtotal'],
                    'imei_number'        => $value['imei_number'],
                ];


                if ($order->status == "completed") {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['qte'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['qte'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }

                    } else {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['qte'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['qte'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }
                    }
                }
            }
            ReservationItem::insert($reservationItem);

            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');

            if ($request->payment['status'] != 'pending') {
                $reservation = Reservation::findOrFail($order->id);
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === reservation->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $reservation);
                }


                try {

                    $total_paid = $reservation->paid_amount + $request['amount'];
                    $due = $reservation->total_price - $total_paid;
                    
                    if ($due === 0.0 || $due < 0.0) {
                        $payment_status = 'paid';
                    } else if ($due != $reservation->total_price) {
                        $payment_status = 'partial';
                    } else if ($due == $reservation->total_price) {
                        $payment_status = 'unpaid';
                    }
                    
                    if($request['amount'] > 0 && $request->payment['status'] != 'pending'){
                        if ($request->payment['discount'] == 'credit card') {
                            $Client = Client::whereId($request->client_id)->first();
                            Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
    
                            // Check if the payment record exists
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();
                            if (!$PaymentWithCreditCard) {
    
                                // Create a new customer and charge the customer with a new credit card
                                $customer = \Stripe\Customer::create([
                                    'source' => $request->token,
                                    'email'  => $Client->email,
                                    'name'   => $Client->name,
                                ]);
    
                                // Charge the Customer instead of the card:
                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer->id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer->id;
    
                            // Check if the payment record not exists
                            } else {
    
                                 // Retrieve the customer ID and card ID
                                $customer_id = $PaymentWithCreditCard->customer_stripe_id;
                                $card_id = $request->card_id;
    
                                // Charge the customer with the new credit card or the selected card
                                if ($request->is_new_credit_card || $request->is_new_credit_card == 'true' || $request->is_new_credit_card === 1) {
                                    // Retrieve the customer
                                    $customer = \Stripe\Customer::retrieve($customer_id);
    
                                    // Create New Source
                                    $card = \Stripe\Customer::createSource(
                                        $customer_id,
                                        [
                                          'source' => $request->token,
                                        ]
                                      );
    
                                    $charge = \Stripe\Charge::create([
                                        'amount'   => $request['amount'] * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer_id,
                                        'source'   => $card->id,
                                    ]);
                                    $PaymentCard['customer_stripe_id'] = $customer_id;
    
                                } else {
                                    $charge = \Stripe\Charge::create([
                                        'amount'   => $request['amount'] * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer_id,
                                        'source'   => $card_id,
                                    ]);
                                    $PaymentCard['customer_stripe_id'] = $customer_id;
                                }
                            }
    
                            $PaymentReservation            = new PaymentReservation();
                            $PaymentReservation->reservation_id   = $order->id;
                            $PaymentReservation->ref       = app('App\Http\Controllers\PaymentReservationsController')->getNumberOrder();
                            $PaymentReservation->date      = Carbon::now();
                            $PaymentReservation->discount = $request->payment['discount'];
                            $PaymentReservation->amount   = $request['amount'];
                            $PaymentReservation->change    = $request['change'];
                            $PaymentReservation->notes     = NULL;
                            $PaymentReservation->user_id   = Auth::user()->id;
                            $PaymentReservation->account_id   = $request->payment['account_id']?$request->payment['account_id']:NULL;
                            $PaymentReservation->save();

                            $account = Account::where('id', $request->payment['account_id'])->exists();

                            if ($account) {
                                // Account exists, perform the update
                                $account = Account::find($request->payment['account_id']);
                                $account->update([
                                    'balance' => $account->balance + $request['amount'],
                                ]);
                            }
    
                            $reservation->update([
                                'paid_amount'    => $total_paid,
                                'payment_status' => $payment_status,
                            ]);
    
                            $PaymentCard['customer_id'] = $request->client_id;
                            $PaymentCard['payment_id']  = $PaymentReservation->id;
                            $PaymentCard['charge_id']   = $charge->id;
                            PaymentWithCreditCard::create($PaymentCard);
    
                            // Paying Method Cash
                        } else {
    
                            PaymentReservation::create([
                                'reservation_id' => $order->id,
                                'ref' => app('App\Http\Controllers\PaymentReservationsController')->getNumberOrder(),
                                'date' => Carbon::now(),
                                'account_id' => $request->payment['account_id']?$request->payment['account_id']:NULL,
                                'discount' => $request->payment['discount'],
                                'amount' => $request['amount'],
                                'change' => $request['change'],
                                'notes' => NULL,
                                'user_id' => Auth::user()->id,
                            ]);

                            $account = Account::where('id', $request->payment['account_id'])->exists();

                            if ($account) {
                                // Account exists, perform the update
                                $account = Account::find($request->payment['account_id']);
                                $account->update([
                                    'balance' => $account->balance + $request['amount'],
                                ]);
                            }
    
                            $reservation->update([
                                'paid_amount' => $total_paid,
                                'payment_status' => $payment_status,
                            ]);
                        }
    
                    }
                } catch (Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
                }
                
            }

        }, 10);

        return response()->json(['success' => true]);
    }


    //------------- UPDATE RESERVATION -----------

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Reservation::class);

        request()->validate([
            'warehouse_id' => 'required',
            'client_id' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id) {

            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_Reservation = Reservation::findOrFail($id);
            
            if (ReservationReturn::where('reservation_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
            }else{
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Reservation->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Reservation);
                }
                $old_reservation_details = ReservationItem::where('reservation_id', $id)->get();
                $new_reservation_details = $request['details'];
                $length = sizeof($new_reservation_details);

                // Get Ids for new Details
                $new_products_id = [];
                foreach ($new_reservation_details as $new_detail) {
                    $new_products_id[] = $new_detail['id'];
                }

                // Init Data with old Parametre
                $old_products_id = [];
                foreach ($old_reservation_details as $key => $value) {
                    $old_products_id[] = $value->id;
                    
                    //check if detail has reservation_unit_id Or Null
                    if($value['reservation_unit_id'] !== null){
                        $old_unit = Unit::where('id', $value['reservation_unit_id'])->first();
                    }else{
                        $product_unit_reservation_id = Product::with('unitReservation')
                        ->where('id', $value['product_id'])
                        ->first();

                        if($product_unit_reservation_id['unitReservation']){
                            $old_unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                        }{
                            $old_unit = NULL;
                        }
                    }

                    if ($current_Reservation->status == "completed") {

                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Reservation->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['qte'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['qte'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Reservation->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();
                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['qte'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['qte'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }
                    // Delete Detail
                    if (!in_array($old_products_id[$key], $new_products_id)) {
                        $ReservationItem = ReservationItem::findOrFail($value->id);
                        $ReservationItem->delete();
                    }
                }


                // Update Data with New request
                foreach ($new_reservation_details as $prd => $prod_detail) {

                    $get_type_product = Product::where('id', $prod_detail['product_id'])->first()->type;

                    
                    if($prod_detail['reservation_unit_id'] !== null || $get_type_product == 'is_service'){
                        $unit_prod = Unit::where('id', $prod_detail['reservation_unit_id'])->first();

                        if ($request['status'] == "completed") {

                            if ($prod_detail['product_variant_id'] !== null) {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $request->warehouse_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->where('product_variant_id', $prod_detail['product_variant_id'])
                                    ->first();

                                if ($product_warehouse && $unit_prod) {
                                    if ($unit_prod->operator == '/') {
                                        $product_warehouse->qte -= $prod_detail['qte'] / $unit_prod->operator_value;
                                    } else {
                                        $product_warehouse->qte -= $prod_detail['qte'] * $unit_prod->operator_value;
                                    }
                                    $product_warehouse->save();
                                }

                            } else {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $request->warehouse_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->first();

                                if ($product_warehouse && $unit_prod) {
                                    if ($unit_prod->operator == '/') {
                                        $product_warehouse->qte -= $prod_detail['qte'] / $unit_prod->operator_value;
                                    } else {
                                        $product_warehouse->qte -= $prod_detail['qte'] * $unit_prod->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
                            }

                        }

                        $reservationItem['reservation_id']      = $id;
                        $reservationItem['date']         = $request['date'];
                        $reservationItem['price']        = $prod_detail['price'];
                        $reservationItem['reservation_unit_id'] = $prod_detail['reservation_unit_id'];
                        $reservationItem['tax_net']       = $prod_detail['tax_percent'];
                        $reservationItem['tax_method']   = $prod_detail['tax_method'];
                        $reservationItem['discount']     = $prod_detail['discount'];
                        $reservationItem['discount_method'] = $prod_detail['discount_Method'];
                        $reservationItem['qte']        = $prod_detail['qte'];
                        $reservationItem['product_id']      = $prod_detail['product_id'];
                        $reservationItem['product_variant_id'] = $prod_detail['product_variant_id'];
                        $reservationItem['total']              = $prod_detail['subtotal'];
                        $reservationItem['imei_number']        = $prod_detail['imei_number'];

                        if (!in_array($prod_detail['id'], $old_products_id)) {
                            $reservationItem['date'] = $request['date'];
                            $reservationItem['reservation_unit_id'] = $unit_prod ? $unit_prod->id : Null;
                            ReservationItem::Create($reservationItem);
                        } else {
                            ReservationItem::where('id', $prod_detail['id'])->update($reservationItem);
                        }
                    }
                }

                $due = $request['total_price'] - $current_Reservation->paid_amount;
                if ($due === 0.0 || $due < 0.0) {
                    $payment_status = 'paid';
                } else if ($due != $request['total_price']) {
                    $payment_status = 'partial';
                } else if ($due == $request['total_price']) {
                    $payment_status = 'unpaid';
                }

                $current_Reservation->update([
                    'date'         => $request['date'],
                    'client_id'    => $request['client_id'],
                    'warehouse_id' => $request['warehouse_id'],
                    'notes'        => $request['notes'],
                    'status'       => $request['status'],
                    'tax_rate'     => $request['tax_rate'],
                    'tax_net'       => $request['tax_net'],
                    'discount'     => $request['discount'],
                    'shipping'     => $request['shipping'],
                    'total_price'   => $request['total_price'],
                    'payment_status' => $payment_status,
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------- Remove RESERVATION BY ID -----------\\

     public function destroy(Request $request, $id)
     {
         $this->authorizeForUser($request->user('api'), 'delete', Reservation::class);
 
         \DB::transaction(function () use ($id, $request) {
             $role = Auth::user()->roles()->first();
             $view_records = Role::findOrFail($role->id)->inRole('record_view');
             $current_Reservation = Reservation::findOrFail($id);
             $old_reservation_details = ReservationItem::where('reservation_id', $id)->get();
             $shipment_data =  Shipment::where('reservation_id', $id)->first();

             if (ReservationReturn::where('reservation_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
            }else{
                
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Reservation->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Reservation);
                }
                foreach ($old_reservation_details as $key => $value) {
                    
                    //check if detail has reservation_unit_id Or Null
                    if($value['reservation_unit_id'] !== null){
                        $old_unit = Unit::where('id', $value['reservation_unit_id'])->first();
                    }else{
                        $product_unit_reservation_id = Product::with('unitReservation')
                        ->where('id', $value['product_id'])
                        ->first();
                        if($product_unit_reservation_id['unitReservation']){
                            $old_unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                        }{
                            $old_unit = NULL;
                        }
                    }

                    if ($current_Reservation->status == "completed") {

                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Reservation->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['qte'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['qte'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Reservation->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();
                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['qte'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['qte'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }
                    
                }

                if($shipment_data){
                    $shipment_data->delete();
                }
                $current_Reservation->details()->delete();
                $current_Reservation->update([
                    'deleted_at' => Carbon::now(),
                    'shipping_status' => NULL,
                ]);


                $Payment_Reservation_data = PaymentReservation::where('reservation_id', $id)->get();
                foreach($Payment_Reservation_data as $Payment_Reservation){
                    if($Payment_Reservation->discount == 'credit card') {
                        $PaymentWithCreditCard = PaymentWithCreditCard::where('payment_id', $Payment_Reservation->id)->first();
                        if($PaymentWithCreditCard){
                            $PaymentWithCreditCard->delete();
                        }
                    }

                    $account = Account::find($Payment_Reservation->account_id);
 
                    if ($account) {
                        $account->update([
                            'balance' => $account->balance - $Payment_Reservation->amount,
                        ]);
                    }

                    $Payment_Reservation->delete();
                }
            }
 
         }, 10);
 
         return response()->json(['success' => true]);
     }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', Reservation::class);

        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $reservation_id) {

                if (ReservationReturn::where('reservation_id', $reservation_id)->where('deleted_at', '=', null)->exists()) {
                    return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
                }else{
                    $current_Reservation = Reservation::findOrFail($reservation_id);
                    $old_reservation_details = ReservationItem::where('reservation_id', $reservation_id)->get();
                    $shipment_data =  Shipment::where('reservation_id', $reservation_id)->first();

                    // Check If User Has Permission view All Records
                    if (!$view_records) {
                        // Check If User->id === current_Reservation->id
                        $this->authorizeForUser($request->user('api'), 'check_record', $current_Reservation);
                    }
                    foreach ($old_reservation_details as $key => $value) {
                    
                         //check if detail has reservation_unit_id Or Null
                        if($value['reservation_unit_id'] !== null){
                            $old_unit = Unit::where('id', $value['reservation_unit_id'])->first();
                        }else{
                            $product_unit_reservation_id = Product::with('unitReservation')
                            ->where('id', $value['product_id'])
                            ->first();
                            if($product_unit_reservation_id['unitReservation']){
                                $old_unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                            }{
                                $old_unit = NULL;
                            }
                        }
        
                        if ($current_Reservation->status == "completed") {
        
                            if ($value['product_variant_id'] !== null) {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $current_Reservation->warehouse_id)
                                    ->where('product_id', $value['product_id'])
                                    ->where('product_variant_id', $value['product_variant_id'])
                                    ->first();
        
                                if ($product_warehouse && $old_unit) {
                                    if ($old_unit->operator == '/') {
                                        $product_warehouse->qte += $value['qte'] / $old_unit->operator_value;
                                    } else {
                                        $product_warehouse->qte += $value['qte'] * $old_unit->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
        
                            } else {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $current_Reservation->warehouse_id)
                                    ->where('product_id', $value['product_id'])
                                    ->first();
                                if ($product_warehouse && $old_unit) {
                                    if ($old_unit->operator == '/') {
                                        $product_warehouse->qte += $value['qte'] / $old_unit->operator_value;
                                    } else {
                                        $product_warehouse->qte += $value['qte'] * $old_unit->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
                            }
                        }
                        
                    }

                    if($shipment_data){
                        $shipment_data->delete();
                    }
                    
                    $current_Reservation->details()->delete();
                    $current_Reservation->update([
                        'deleted_at' => Carbon::now(),
                        'shipping_status' => NULL,
                    ]);


                    $Payment_Reservation_data = PaymentReservation::where('reservation_id', $reservation_id)->get();
                    foreach($Payment_Reservation_data as $Payment_Reservation){
                        if($Payment_Reservation->discount == 'credit card') {
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('payment_id', $Payment_Reservation->id)->first();
                            if($PaymentWithCreditCard){
                                $PaymentWithCreditCard->delete();
                            }
                        }

                        $account = Account::find($Payment_Reservation->account_id);
 
                        if ($account) {
                            $account->update([
                                'balance' => $account->balance - $Payment_Reservation->amount,
                            ]);
                        }

                        $Payment_Reservation->delete();
                    }
                }
            }

        }, 10);

        return response()->json(['success' => true]);
    }

   
    //---------------- Get Details Reservation-----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Reservation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $reservation_data = Reservation::with('details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === reservation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $reservation_data);
        }

        $reservation_details['ref'] = $reservation_data->ref;
        $reservation_details['date'] = $reservation_data->date;
        $reservation_details['note'] = $reservation_data->notes;
        $reservation_details['status'] = $reservation_data->status;
        $reservation_details['warehouse'] = $reservation_data['warehouse']->name;
        $reservation_details['discount'] = $reservation_data->discount;
        $reservation_details['shipping'] = $reservation_data->shipping;
        $reservation_details['tax_rate'] = $reservation_data->tax_rate;
        $reservation_details['tax_net'] = $reservation_data->tax_net;
        $reservation_details['client_name'] = $reservation_data['client']->name;
        $reservation_details['client_phone'] = $reservation_data['client']->phone;
        $reservation_details['client_adr'] = $reservation_data['client']->adresse;
        $reservation_details['client_email'] = $reservation_data['client']->email;
        $reservation_details['client_tax'] = $reservation_data['client']->tax_number;
        $reservation_details['total_price'] = number_format($reservation_data->total_price, 2, '.', '');
        $reservation_details['paid_amount'] = number_format($reservation_data->paid_amount, 2, '.', '');
        $reservation_details['due'] = number_format($reservation_details['total_price'] - $reservation_details['paid_amount'], 2, '.', '');
        $reservation_details['payment_status'] = $reservation_data->payment_status;

        if (ReservationReturn::where('reservation_id', $id)->where('deleted_at', '=', null)->exists()) {
            $sellReturn = ReservationReturn::where('reservation_id', $id)->where('deleted_at', '=', null)->first();
            $reservation_details['reservation_return_id'] = $sellReturn->id;
            $reservation_details['reservation_has_return'] = 'yes';
        }else{
            $reservation_details['reservation_has_return'] = 'no';
        }

        foreach ($reservation_data['details'] as $detail) {

             //check if detail has reservation_unit_id Or Null
             if($detail->reservation_unit_id !== null){
                $unit = Unit::where('id', $detail->reservation_unit_id)->first();
            }else{
                $product_unit_reservation_id = Product::with('unitReservation')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_reservation_id['unitReservation']){
                    $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                }{
                    $unit = NULL;
                }
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name .']'. $detail['product']['name'];
 
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['qte'] = $detail->qte;
            $data['total'] = $detail->total;
            $data['price'] = $detail->price;
            $data['unit_reservation'] = $unit?$unit->ShortName:'';

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->tax_net * (($detail->price - $data['DiscountNet']) / 100);
            $data['price'] = $detail->price;
            $data['discount'] = $detail->discount;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'details' => $details,
            'reservation' => $reservation_details,
            'company' => $company,
        ]);

    }

    //-------------- Print Invoice ---------------\\

    public function Print_Invoice_POS(Request $request, $id)
    {
        $helpers = new helpers();
        $details = array();

        $reservation = Reservation::with('details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $item['id'] = $reservation->id;
        $item['ref'] = $reservation->ref;
        $item['date'] = $reservation->date;
        $item['discount'] = number_format($reservation->discount, 2, '.', '');
        $item['shipping'] = number_format($reservation->shipping, 2, '.', '');
        $item['taxe'] =     number_format($reservation->tax_net, 2, '.', '');
        $item['tax_rate'] = $reservation->tax_rate;
        $item['client_name'] = $reservation['client']->name;
        $item['warehouse_name'] = $reservation['warehouse']->name;
        $item['total_price'] = number_format($reservation->total_price, 2, '.', '');
        $item['paid_amount'] = number_format($reservation->paid_amount, 2, '.', '');

        foreach ($reservation['details'] as $detail) {

             //check if detail has reservation_unit_id Or Null
             if($detail->reservation_unit_id !== null){
                $unit = Unit::where('id', $detail->reservation_unit_id)->first();
            }else{
                $product_unit_reservation_id = Product::with('unitReservation')
                ->where('id', $detail->product_id)
                ->first();
                if($product_unit_reservation_id['unitReservation']){
                    $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                }{
                    $unit = NULL;
                }

            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                    
                } else {
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];
                }
                
           
            $data['qte'] = number_format($detail->qte, 2, '.', '');
            $data['total'] = number_format($detail->total, 2, '.', '');
            $data['unit_reservation'] = $unit?$unit->ShortName:'';

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $payments = PaymentReservation::with('reservation')
            ->where('reservation_id', $id)
            ->orderBy('id', 'DESC')
            ->get();

        $settings = Setting::where('deleted_at', '=', null)->first();
        $pos_settings = PosSetting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        return response()->json([
            'symbol' => $symbol,
            'payments' => $payments,
            'setting' => $settings,
            'pos_settings' => $pos_settings,
            'reservation' => $item,
            'details' => $details,
        ]);

    }

    //------------- GET PAYMENTS RESERVATION -----------\\

    public function Payments_Reservation(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentReservation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Reservation = Reservation::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Reservation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Reservation);
        }

        $payments = PaymentReservation::with('reservation')
            ->where('reservation_id', $id)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $Reservation->total_price - $Reservation->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);

    }

    //------------- reference Number Order RESERVATION -----------\\

    public function getNumberOrder()
    {

        $last = DB::table('reservations')->latest('id')->first();

        if ($last) {
            $item = $last->ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'SL_1111';
        }
        return $code;
    }

    //------------- RESERVATION PDF -----------\\

    public function Reservation_PDF(Request $request, $id)
    {

        $details = array();
        $helpers = new helpers();
        $reservation_data = Reservation::with('details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $reservation['client_name'] = $reservation_data['client']->name;
        $reservation['client_phone'] = $reservation_data['client']->phone;
        $reservation['client_adr'] = $reservation_data['client']->adresse;
        $reservation['client_email'] = $reservation_data['client']->email;
        $reservation['client_tax'] = $reservation_data['client']->tax_number;
        $reservation['tax_net'] = number_format($reservation_data->tax_net, 2, '.', '');
        $reservation['discount'] = number_format($reservation_data->discount, 2, '.', '');
        $reservation['shipping'] = number_format($reservation_data->shipping, 2, '.', '');
        $reservation['status'] = $reservation_data->status;
        $reservation['ref'] = $reservation_data->ref;
        $reservation['date'] = $reservation_data->date;
        $reservation['total_price'] = number_format($reservation_data->total_price, 2, '.', '');
        $reservation['paid_amount'] = number_format($reservation_data->paid_amount, 2, '.', '');
        $reservation['due'] = number_format($reservation['total_price'] - $reservation['paid_amount'], 2, '.', '');
        $reservation['payment_status'] = $reservation_data->payment_status;

        $detail_id = 0;
        foreach ($reservation_data['details'] as $detail) {

            //check if detail has reservation_unit_id Or Null
            if($detail->reservation_unit_id !== null){
                $unit = Unit::where('id', $detail->reservation_unit_id)->first();
            }else{
                $product_unit_reservation_id = Product::with('unitReservation')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_reservation_id['unitReservation']){
                    $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                }{
                    $unit = NULL;
                }

            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

                $data['detail_id'] = $detail_id += 1;
                $data['qte'] = number_format($detail->qte, 2, '.', '');
                $data['total'] = number_format($detail->total, 2, '.', '');
                $data['unitReservation'] = $unit?$unit->ShortName:'';
                $data['price'] = number_format($detail->price, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->price * $detail->discount / 100, 2, '.', '');
            }

            $tax_price = $detail->tax_net * (($detail->price - $data['DiscountNet']) / 100);
            $data['price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.reservation_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'reservation' => $reservation,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);
        return $pdf->download('reservation.pdf');

    }

    //----------------Show Form Create Reservation ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Reservation::class);

       //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', '=', null)->get(['id', 'account_name']);

        $stripe_key = config('app.STRIPE_KEY');

        return response()->json([
            'stripe_key' => $stripe_key,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
        ]);

    }

      //------------- Show Form Edit Reservation -----------\\

      public function edit(Request $request, $id)
      {
        if (ReservationReturn::where('reservation_id', $id)->where('deleted_at', '=', null)->exists()) {
            return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
        }else{
          $this->authorizeForUser($request->user('api'), 'update', Reservation::class);
          $role = Auth::user()->roles()->first();
          $view_records = Role::findOrFail($role->id)->inRole('record_view');
          $Reservation_data = Reservation::with('details.product.unitReservation')
              ->where('deleted_at', '=', null)
              ->findOrFail($id);
          $details = array();
          // Check If User Has Permission view All Records
          if (!$view_records) {
              // Check If User->id === reservation->id
              $this->authorizeForUser($request->user('api'), 'check_record', $Reservation_data);
          }
  
          if ($Reservation_data->client_id) {
              if (Client::where('id', $Reservation_data->client_id)
                  ->where('deleted_at', '=', null)
                  ->first()) {
                  $reservation['client_id'] = $Reservation_data->client_id;
              } else {
                  $reservation['client_id'] = '';
              }
          } else {
              $reservation['client_id'] = '';
          }
  
          if ($Reservation_data->warehouse_id) {
              if (Warehouse::where('id', $Reservation_data->warehouse_id)
                  ->where('deleted_at', '=', null)
                  ->first()) {
                  $reservation['warehouse_id'] = $Reservation_data->warehouse_id;
              } else {
                  $reservation['warehouse_id'] = '';
              }
          } else {
              $reservation['warehouse_id'] = '';
          }
  
          $reservation['date'] = $Reservation_data->date;
          $reservation['tax_rate'] = $Reservation_data->tax_rate;
          $reservation['tax_net'] = $Reservation_data->tax_net;
          $reservation['discount'] = $Reservation_data->discount;
          $reservation['shipping'] = $Reservation_data->shipping;
          $reservation['status'] = $Reservation_data->status;
          $reservation['notes'] = $Reservation_data->notes;
  
          $detail_id = 0;
          foreach ($Reservation_data['details'] as $detail) {

                //check if detail has reservation_unit_id Or Null
                if($detail->reservation_unit_id !== null){
                    $unit = Unit::where('id', $detail->reservation_unit_id)->first();
                    $data['no_unit'] = 1;
                }else{
                    $product_unit_reservation_id = Product::with('unitReservation')
                    ->where('id', $detail->product_id)
                    ->first();

                    if($product_unit_reservation_id['unitReservation']){
                        $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                    }{
                        $unit = NULL;
                    }
    
                    $data['no_unit'] = 0;
                }

        
              if ($detail->product_variant_id) {
                  $item_product = product_warehouse::where('product_id', $detail->product_id)
                      ->where('deleted_at', '=', null)
                      ->where('product_variant_id', $detail->product_variant_id)
                      ->where('warehouse_id', $Reservation_data->warehouse_id)
                      ->first();
  
                  $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                      ->where('id', $detail->product_variant_id)->first();
  
                  $item_product ? $data['del'] = 0 : $data['del'] = 1;
                  $data['product_variant_id'] = $detail->product_variant_id;
                  $data['code'] = $productsVariants->code;
                  $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                 
                  if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                  } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                  } else {
                    $stock = 0;
                  }
  
              } else {
                  $item_product = product_warehouse::where('product_id', $detail->product_id)
                      ->where('deleted_at', '=', null)->where('warehouse_id', $Reservation_data->warehouse_id)
                      ->where('product_variant_id', '=', null)->first();
  
                  $item_product ? $data['del'] = 0 : $data['del'] = 1;
                  $data['product_variant_id'] = null;
                  $data['code'] = $detail['product']['code'];
                  $data['name'] = $detail['product']['name'];

                  if ($unit && $unit->operator == '/') {
                        $stock= $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                  } else {
                    $stock = 0;
                  }
  
                }
                
                $data['id'] = $detail->id;
                $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
                $data['product_type'] = $detail['product']['type'];
                $data['detail_id'] = $detail_id += 1;
                $data['product_id'] = $detail->product_id;
                $data['total'] = $detail->total;
                $data['qte'] = $detail->qte;
                $data['qte_copy'] = $detail->qte;
                $data['etat'] = 'current';
                $data['unitReservation'] = $unit?$unit->ShortName:'';
                $data['reservation_unit_id'] = $unit?$unit->id:'';
                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->price * $detail->discount / 100;
                }

                $tax_price = $detail->tax_net * (($detail->price - $data['DiscountNet']) / 100);
                $data['price'] = $detail->price;
                
                $data['tax_percent'] = $detail->tax_net;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_price'] = $detail->price - $data['DiscountNet'];
                    $data['taxe'] = $tax_price;
                    $data['subtotal'] = ($data['Net_price'] * $data['qte']) + ($tax_price * $data['qte']);
                } else {
                    $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                    $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_price'] * $data['qte']) + ($tax_price * $data['qte']);
                }


               $details[] = $data;
          }
        
         //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

          $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
  
          return response()->json([
              'details' => $details,
              'reservation' => $reservation,
              'clients' => $clients,
              'warehouses' => $warehouses,
          ]);
        }
  
      }



    //------------- Show Form Convert To Reservation -----------\\

    public function Elemens_Change_To_Reservation(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Quotation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Quotation = Quotation::with('details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Quotation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Quotation);
        }

        if ($Quotation->client_id) {
            if (Client::where('id', $Quotation->client_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $reservation['client_id'] = $Quotation->client_id;
            } else {
                $reservation['client_id'] = '';
            }
        } else {
            $reservation['client_id'] = '';
        }

        if ($Quotation->warehouse_id) {
            if (Warehouse::where('id', $Quotation->warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $reservation['warehouse_id'] = $Quotation->warehouse_id;
            } else {
                $reservation['warehouse_id'] = '';
            }
        } else {
            $reservation['warehouse_id'] = '';
        }

        $reservation['date'] = $Quotation->date;
        $reservation['tax_net'] = $Quotation->tax_net;
        $reservation['tax_rate'] = $Quotation->tax_rate;
        $reservation['discount'] = $Quotation->discount;
        $reservation['shipping'] = $Quotation->shipping;
        $reservation['status'] = 'completed';
        $reservation['notes'] = $Quotation->notes;

        $detail_id = 0;
        foreach ($Quotation['details'] as $detail) {
           
                //check if detail has reservation_unit_id Or Null
                if($detail->reservation_unit_id !== null || $detail['product']['type'] == 'is_service'){
                    $unit = Unit::where('id', $detail->reservation_unit_id)->first();

                if ($detail->product_variant_id) {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->where('warehouse_id', $Quotation->warehouse_id)
                        ->where('deleted_at', '=', null)
                        ->first();
                    $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                        ->where('id', $detail->product_variant_id)->where('deleted_at', null)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = $detail->product_variant_id;
                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }

                } else {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('warehouse_id', $Quotation->warehouse_id)
                        ->where('product_variant_id', '=', null)
                        ->where('deleted_at', '=', null)
                        ->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = null;
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }
                }
                
                $data['id'] = $id;
                $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
                $data['product_type'] = $detail['product']['type'];
                $data['detail_id'] = $detail_id += 1;
                $data['qte'] = $detail->qte;
                $data['product_id'] = $detail->product_id;
                $data['total'] = $detail->total;
                $data['etat'] = 'current';
                $data['qte_copy'] = $detail->qte;
                $data['unitReservation'] = $unit?$unit->ShortName:'';
                $data['reservation_unit_id'] = $unit?$unit->id:'';

                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->price * $detail->discount / 100;
                }
                $tax_price = $detail->tax_net * (($detail->price - $data['DiscountNet']) / 100);
                $data['price'] = $detail->price;
                $data['tax_percent'] = $detail->tax_net;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_price'] = $detail->price - $data['DiscountNet'];
                    $data['taxe'] = $tax_price;
                    $data['subtotal'] = ($data['Net_price'] * $data['qte']) + ($tax_price * $data['qte']);
                } else {
                    $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                    $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_price'] * $data['qte']) + ($tax_price * $data['qte']);
                }

                $details[] = $data;
            }
        }

       //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }
         
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'details' => $details,
            'reservation' => $reservation,
            'clients' => $clients,
            'warehouses' => $warehouses,
        ]);

    }

    
    //------------------- get_Products_by_reservation -----------------\\

    public function get_Products_by_reservation(Request $request , $id)
    {

        $this->authorizeForUser($request->user('api'), 'create', ReservationReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $ReservationReturn = Reservation::with('details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === ReservationReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $ReservationReturn);
        }

        $Return_detail['client_id'] = $ReservationReturn->client_id;
        $Return_detail['warehouse_id'] = $ReservationReturn->warehouse_id;
        $Return_detail['reservation_id'] = $ReservationReturn->id;
        $Return_detail['tax_rate'] = 0;
        $Return_detail['tax_net'] = 0;
        $Return_detail['discount'] = 0;
        $Return_detail['shipping'] = 0;
        $Return_detail['status'] = "received";
        $Return_detail['notes'] = "";

        $detail_id = 0;
        foreach ($ReservationReturn['details'] as $detail) {

            //check if detail has reservation_unit_id Or Null
            if($detail->reservation_unit_id !== null){
                $unit = Unit::where('id', $detail->reservation_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_reservation_id = Product::with('unitReservation')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_reservation_id['unitReservation']){
                    $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                }{
                    $unit = NULL;
                }

                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $ReservationReturn->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $ReservationReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            }

            $data['id'] = $detail->id;
            $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
            $data['detail_id'] = $detail_id += 1;
            $data['qte'] = $detail->qte;
            $data['reservation_qte'] = $detail->qte;
            $data['product_id'] = $detail->product_id;
            $data['unitReservation'] = $unit->ShortName;
            $data['reservation_unit_id'] = $unit->id;
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->tax_net * (($detail->price - $data['DiscountNet']) / 100);
            $data['price'] = $detail->price;
            $data['tax_percent'] = $detail->tax_net;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {

                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['subtotal'] = ($data['Net_price'] * $data['qte']) + ($tax_price * $data['qte']);
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_price'] * $data['qte']) + ($tax_price * $data['qte']);
            }

            $details[] = $data;
        }


        return response()->json([
            'details' => $details,
            'reservation_return' => $Return_detail,
        ]);

    }



     //------------- Send reservation on Email -----------\\

     public function Send_Email(Request $request)
     {
        $this->authorizeForUser($request->user('api'), 'view', Reservation::class);

          //reservation
          $reservation = Reservation::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);
 
          $helpers = new helpers();
          $currency = $helpers->Get_Currency();
 
          //settings
          $settings = Setting::where('deleted_at', '=', null)->first();
      
          //the custom msg of reservation
          $emailMessage  = EmailMessage::where('name', 'reservation')->first();
  
          if($emailMessage){
              $message_body = $emailMessage->body;
              $message_subject = $emailMessage->subject;
          }else{
              $message_body = '';
              $message_subject = '';
          }
  
          //Tags
          $random_number = Str::random(10);
          $invoice_url = url('/api/reservation_pdf/' . $request->id.'?'.$random_number);
          $invoice_number = $reservation->ref;
  
          $total_amount = $currency .' '.number_format($reservation->total_price, 2, '.', ',');
          $paid_amount  = $currency .' '.number_format($reservation->paid_amount, 2, '.', ',');
          $due_amount   = $currency .' '.number_format($reservation->total_price - $reservation->paid_amount, 2, '.', ',');
  
          $contact_name = $reservation['client']->name;
          $business_name = $settings->CompanyName;
  
          //receiver email
          $receiver_email = $reservation['client']->email;
  
          //replace the text with tags
          $message_body = str_replace('{contact_name}', $contact_name, $message_body);
          $message_body = str_replace('{business_name}', $business_name, $message_body);
          $message_body = str_replace('{invoice_url}', $invoice_url, $message_body);
          $message_body = str_replace('{invoice_number}', $invoice_number, $message_body);
  
          $message_body = str_replace('{total_amount}', $total_amount, $message_body);
          $message_body = str_replace('{paid_amount}', $paid_amount, $message_body);
          $message_body = str_replace('{due_amount}', $due_amount, $message_body);
 
         $email['subject'] = $message_subject;
         $email['body'] = $message_body;
         $email['company_name'] = $business_name;
 
         $this->Set_config_mail(); 
 
         $mail = Mail::to($receiver_email)->send(new CustomEmail($email));
 
         return $mail;
     }
 
 

     //-------------------Sms Notifications -----------------\\
 
     public function Send_SMS(Request $request)
     {
        $this->authorizeForUser($request->user('api'), 'view', Reservation::class);

         //reservation
         $reservation = Reservation::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);
 
         $helpers = new helpers();
         $currency = $helpers->Get_Currency();
         
         //settings
         $settings = Setting::where('deleted_at', '=', null)->first();
     
         $default_sms_gateway = sms_gateway::where('id' , $settings->sms_gateway)
         ->where('deleted_at', '=', null)->first();

         //the custom msg of reservation
         $smsMessage  = SMSMessage::where('name', 'reservation')->first();
 
         if($smsMessage){
             $message_text = $smsMessage->text;
         }else{
             $message_text = '';
         }
 
         //Tags
         $random_number = Str::random(10);
         $invoice_url = url('/api/reservation_pdf/' . $request->id.'?'.$random_number);
         $invoice_number = $reservation->ref;
 
         $total_amount = $currency.' '.number_format($reservation->total_price, 2, '.', ',');
         $paid_amount  = $currency.' '.number_format($reservation->paid_amount, 2, '.', ',');
         $due_amount   = $currency.' '.number_format($reservation->total_price - $reservation->paid_amount, 2, '.', ',');
 
         $contact_name = $reservation['client']->name;
         $business_name = $settings->CompanyName;
 
         //receiver Number
         $receiverNumber = $reservation['client']->phone;
 
         //replace the text with tags
         $message_text = str_replace('{contact_name}', $contact_name, $message_text);
         $message_text = str_replace('{business_name}', $business_name, $message_text);
         $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
         $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);
 
         $message_text = str_replace('{total_amount}', $total_amount, $message_text);
         $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
         $message_text = str_replace('{due_amount}', $due_amount, $message_text);
 
         //twilio
         if($default_sms_gateway->title == "twilio"){
             try {
     
                 $account_sid = env("TWILIO_SID");
                 $auth_token = env("TWILIO_TOKEN");
                 $twilio_number = env("TWILIO_FROM");
     
                 $client = new Client_Twilio($account_sid, $auth_token);
                 $client->messages->create($receiverNumber, [
                     'from' => $twilio_number, 
                     'body' => $message_text]);
         
             } catch (Exception $e) {
                 return response()->json(['message' => $e->getMessage()], 500);
             }
         //nexmo
         }
        //  elseif($default_sms_gateway->title == "nexmo"){
        //      try {
 
        //          $basic  = new \Nexmo\Client\Credentials\Basic(env("NEXMO_KEY"), env("NEXMO_SECRET"));
        //          $client = new \Nexmo\Client($basic);
        //          $nexmo_from = env("NEXMO_FROM");
         
        //          $message = $client->message()->send([
        //              'to' => $receiverNumber,
        //              'from' => $nexmo_from,
        //              'text' => $message_text
        //          ]);
                         
        //      } catch (Exception $e) {
        //          return response()->json(['message' => $e->getMessage()], 500);
        //      }
 
        //  //---- infobip
        //  }
         elseif($default_sms_gateway->title == "infobip"){
 
             $BASE_URL = env("base_url");
             $API_KEY = env("api_key");
             $SENDER = env("sender_from");
 
             $configuration = (new Configuration())
                 ->setHost($BASE_URL)
                 ->setApiKeyPrefix('Authorization', 'App')
                 ->setApiKey('Authorization', $API_KEY);
             
             $client = new Client_guzzle();
             
             $sendSmsApi = new SendSMSApi($client, $configuration);
             $destination = (new SmsDestination())->setTo($receiverNumber);
             $message = (new SmsTextualMessage())
                 ->setFrom($SENDER)
                 ->setText($message_text)
                 ->setDestinations([$destination]);
                 
             $request = (new SmsAdvancedTextualRequest())->setMessages([$message]);
             
             try {
                 $smsResponse = $sendSmsApi->sendSmsMessage($request);
                 echo ("Response body: " . $smsResponse);
             } catch (Throwable $apiException) {
                 echo("HTTP Code: " . $apiException->getCode() . "\n");
             }
             
         }
 
         return response()->json(['success' => true]);
 
         
     }


      // reservations_send_whatsapp
    public function reservations_send_whatsapp(Request $request)
    {

         //reservation
         $reservation = Reservation::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);
 
         $helpers = new helpers();
         $currency = $helpers->Get_Currency();
         
         //settings
         $settings = Setting::where('deleted_at', '=', null)->first();

         //the custom msg of reservation
         $smsMessage  = SMSMessage::where('name', 'reservation')->first();
 
         if($smsMessage){
             $message_text = $smsMessage->text;
         }else{
             $message_text = '';
         }
 
         //Tags
         $random_number = Str::random(10);
         $invoice_url = url('/api/reservation_pdf/' . $request->id.'?'.$random_number);
         $invoice_number = $reservation->ref;
 
         $total_amount = $currency.' '.number_format($reservation->total_price, 2, '.', ',');
         $paid_amount  = $currency.' '.number_format($reservation->paid_amount, 2, '.', ',');
         $due_amount   = $currency.' '.number_format($reservation->total_price - $reservation->paid_amount, 2, '.', ',');
 
         $contact_name = $reservation['client']->name;
         $business_name = $settings->CompanyName;
 
         //receiver Number
         $receiverNumber = $reservation['client']->phone;

          // Check if the phone number is empty or null
        if (empty($receiverNumber) || $receiverNumber == null || $receiverNumber == 'null' || $receiverNumber == '') {
            return response()->json(['error' => 'Phone number is missing'], 400);
        }
 
 
         //replace the text with tags
         $message_text = str_replace('{contact_name}', $contact_name, $message_text);
         $message_text = str_replace('{business_name}', $business_name, $message_text);
         $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
         $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);
 
         $message_text = str_replace('{total_amount}', $total_amount, $message_text);
         $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
         $message_text = str_replace('{due_amount}', $due_amount, $message_text);
 

        return response()->json(['message' => $message_text , 'phone' => $receiverNumber ]);


    }


    // get_today_reservations
    public function get_today_reservations(Request $request){

        $this->authorizeForUser($request->user('api'), 'Reservations_pos', Reservation::class);
        $today = Carbon::today()->toDateString();

        $data['today'] = $today;

        $data['total_reservations_amount'] = Reservation::where('deleted_at', '=', null)
        ->whereDate('date', $today)
        ->sum('total_price');

        $data['total_amount_paid'] = Reservation::where('deleted_at', '=', null)
        ->whereDate('date', $today)
        ->sum('paid_amount');

        $data['total_cash'] = PaymentReservation::where('deleted_at', '=', null)
        ->whereDate('date', $today)
        ->where('discount', 'Cash')
        ->sum('amount');

        $data['total_credit_card'] = PaymentReservation::where('deleted_at', '=', null)
        ->whereDate('date', $today)
        ->where('discount', 'credit card')
        ->sum('amount');

        $data['total_cheque'] = PaymentReservation::where('deleted_at', '=', null)
        ->whereDate('date', $today)
        ->where('discount', 'cheque')
        ->sum('amount');

        return response()->json($data);


    }

    //------------- GET ALL POSTS (ROOMS) -----------\\
    public function get_posts(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Post::class);

        $query = Post::with('reservations');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $orderBy = $request->query('order_by', 'created_at');
        $direction = $request->query('order_direction', 'desc');
        $query->orderBy($orderBy, $direction);

        $perPage = $request->query('per_page', 10);
        $posts = $query->paginate($perPage);

        return response()->json($posts);
    }

    //------------- GET ALL SERVICES -----------\\
    public function get_services(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Service::class);
        
        $query = Service::with('reservations');

        if ($search = $request->query('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('price', 'like', "%{$search}%")
                    ->orWhere('unit_per_minute', 'like', "%{$search}%");
            });
        }

        $orderBy = $request->query('order_by', 'created_at');
        $orderDirection = $request->query('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $perPage = $request->query('per_page', 10);
        $services = $query->paginate($perPage);

        return response()->json($services);
    }

    //------------- CHECK POST AVAILABILITY -----------\\
    public function check_post_availability(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Post::class);

        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
        ]);

        $post = Post::findOrFail($request->post_id);
        
        // Check if there's any active reservation for this post in the given time range
        $conflictingReservation = Reservation::where('post_id', $request->post_id)
            ->where('deleted_at', null)
            ->where(function($query) use ($request) {
                $query->whereBetween('started_at', [$request->started_at, $request->ended_at])
                    ->orWhereBetween('ended_at', [$request->started_at, $request->ended_at])
                    ->orWhere(function($q) use ($request) {
                        $q->where('started_at', '<=', $request->started_at)
                          ->where('ended_at', '>=', $request->ended_at);
                    });
            })
            ->first();

        return response()->json([
            'post' => $post,
            'is_available' => !$conflictingReservation,
            'conflicting_reservation' => $conflictingReservation
        ]);
    }

    //------------- CREATE ROOM RESERVATION -----------\\
    public function create_room_reservation(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Reservation::class);

        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'service_id' => 'required|exists:services,id',
            'client_id' => 'required|exists:clients,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
            'notes' => 'nullable|string',
        ]);

        \DB::transaction(function () use ($request) {
            // Check availability first
            $conflictingReservation = Reservation::where('post_id', $request->post_id)
                ->where('deleted_at', null)
                ->where(function($query) use ($request) {
                    $query->whereBetween('started_at', [$request->started_at, $request->ended_at])
                        ->orWhereBetween('ended_at', [$request->started_at, $request->ended_at])
                        ->orWhere(function($q) use ($request) {
                            $q->where('started_at', '<=', $request->started_at)
                              ->where('ended_at', '>=', $request->ended_at);
                        });
                })
                ->first();

            if ($conflictingReservation) {
                throw new \Exception('This room is not available for the selected time period.');
            }

            // Calculate duration in minutes
            $startedAt = Carbon::parse($request->started_at);
            $endedAt = Carbon::parse($request->ended_at);
            $durationMinutes = $endedAt->diffInMinutes($startedAt);

            // Get service details
            $service = Service::findOrFail($request->service_id);
            $totalPrice = ($service->price * $service->unit_per_minute * $durationMinutes) / 60; // Convert to hours

            // Create reservation
            $reservation = new Reservation();
            $reservation->ref = $this->getNumberOrder();
            $reservation->post_id = $request->post_id;
            $reservation->service_id = $request->service_id;
            $reservation->client_id = $request->client_id;
            $reservation->warehouse_id = $request->warehouse_id;
            $reservation->started_at = $request->started_at;
            $reservation->ended_at = $request->ended_at;
            $reservation->total_price = $totalPrice;
            $reservation->paid_amount = 0;
            $reservation->payment_status = 'unpaid';
            $reservation->status = 'pending';
            $reservation->notes = $request->notes;
            $reservation->user_id = Auth::user()->id;
            $reservation->date = Carbon::now();
            $reservation->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Room reservation created successfully']);
    }

    //------------- CREATE DRAFT ROOM RESERVATION -----------\\
    public function create_draft_room_reservation(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Reservation::class);

        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'service_id' => 'required|exists:services,id',
            'client_id' => 'required|exists:clients,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
            'notes' => 'nullable|string',
        ]);

        \DB::transaction(function () use ($request) {
            // Calculate duration in minutes
            $startedAt = Carbon::parse($request->started_at);
            $endedAt = Carbon::parse($request->ended_at);
            $durationMinutes = $endedAt->diffInMinutes($startedAt);

            // Get service details
            $service = Service::findOrFail($request->service_id);
            $totalPrice = ($service->price * $service->unit_per_minute * $durationMinutes) / 60; // Convert to hours

            // Create draft reservation
            $reservation = new Reservation();
            $reservation->ref = $this->getNumberOrder();
            $reservation->post_id = $request->post_id;
            $reservation->service_id = $request->service_id;
            $reservation->client_id = $request->client_id;
            $reservation->warehouse_id = $request->warehouse_id;
            $reservation->started_at = $request->started_at;
            $reservation->ended_at = $request->ended_at;
            $reservation->total_price = $totalPrice;
            $reservation->paid_amount = 0;
            $reservation->payment_status = 'unpaid';
            $reservation->status = 'draft';
            $reservation->notes = $request->notes;
            $reservation->user_id = Auth::user()->id;
            $reservation->date = Carbon::now();
            $reservation->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Draft room reservation created successfully']);
    }
    
}
