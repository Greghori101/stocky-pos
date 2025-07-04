<?php

namespace App\Http\Controllers;

use App\Mail\ReturnMail;
use App\Models\Client;
use App\Models\Unit;
use App\Models\PaymentReservationReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Role;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\ReservationReturn;
use App\Models\ReservationItemReturn;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Account;
use App\Models\UserWarehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\sms_gateway;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class ReservationsReturnController extends BaseController
{

    //------------ GET ALL Reservation Return--------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', ReservationReturn::class);
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
            6 => '=',
        );
        $columns = array(
            0 => 'red',
            1 => 'status',
            2 => 'client_id',
            3 => 'payment_status',
            4 => 'warehouse_id',
            5 => 'date',
            6 => 'reservation_id',
        );
        $data = array();

        // Check If User Has Permission View  All Records
        $ReservationReturn = ReservationReturn::with('reservation','facture', 'client', 'warehouse')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($ReservationReturn, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('red', 'LIKE', "%{$request->search}%")
                        ->orWhere('status', 'LIKE', "%{$request->search}%")
                        ->orWhere('total_price', $request->search)
                        ->orWhere('payment_status', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('reservation', function ($q) use ($request) {
                                $q->where('red', 'LIKE', "%{$request->search}%");
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
        $ReservationReturn = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($ReservationReturn as $Reservation_Return) {

            $item['id'] = $Reservation_Return->id;
            $item['date'] = $Reservation_Return->date;
            $item['red'] = $Reservation_Return->red;
            $item['discount'] = $Reservation_Return->discount;
            $item['shipping'] = $Reservation_Return->shipping;
            $item['status'] = $Reservation_Return->status;
            $item['qte_return'] = $Reservation_Return->qte_retour;
            $item['warehouse_name'] = $Reservation_Return['warehouse']->name;
            $item['reservation_ref'] = $Reservation_Return['reservation']?$Reservation_Return['reservation']->red:'---';
            $item['reservation_id'] = $Reservation_Return['reservation']?$Reservation_Return['reservation']->id:NULL;
            $item['client_id'] = $Reservation_Return['client']->id;
            $item['client_name'] = $Reservation_Return['client']->name;
            $item['client_email'] = $Reservation_Return['client']->email;
            $item['client_tele'] = $Reservation_Return['client']->phone;
            $item['client_code'] = $Reservation_Return['client']->code;
            $item['client_adr'] = $Reservation_Return['client']->adresse;
            $item['total_price'] = number_format($Reservation_Return['total_price'], 2, '.', '');
            $item['paid_amount'] = number_format($Reservation_Return['paid_amount'], 2, '.', '');
            $item['due'] = number_format($item['total_price'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Reservation_Return['payment_status'];

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $reservations = Reservation::where('deleted_at', '=', null)->get(['id', 'red']);
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
            'totalRows' => $totalRows,
            'reservation_Return' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'reservations' => $reservations,
            'accounts' => $accounts,
        ]);

    }

    //------------ Store new Reservation Return --------------\\

    public function store(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', ReservationReturn::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'status' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new ReservationReturn;

            $order->date = $request->date;
            $order->red = $this->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->reservation_id = $request->reservation_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->tax_net = $request->tax_net;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->total_price = $request->total_price;
            $order->status = $request->status;
            $order->payment_status = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {
                $unit = Unit::where('id', $value['reservation_unit_id'])->first();

                $reservationItem[] = [
                    'reservation_return_id' => $order->id,
                    'qte' => $value['qte'],
                    'price' => $value['price'],
                    'reservation_unit_id' =>  $value['reservation_unit_id'],
                    'tax_net' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'imei_number' => $value['imei_number'],
                ];

                if ($order->status == "received") {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte += $value['qte'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte += $value['qte'] * $unit->operator_value;
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
                                $product_warehouse->qte += $value['qte'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte += $value['qte'] * $unit->operator_value;
                            }

                            $product_warehouse->save();
                        }
                    }
                }

            }
            ReservationItemReturn::insert($reservationItem);
        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Update Return Reservation--------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', ReservationReturn::class);

        \DB::transaction(function () use ($request, $id) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_ReservationReturn = ReservationReturn::findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === ReservationReturn->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_ReservationReturn);
            }
            $old_return_details = ReservationItemReturn::where('reservation_return_id', $id)->get();
            $new_return_details = $request['details'];
            $length = sizeof($new_return_details);

            // Get Ids details
            $new_products_id = [];
            foreach ($new_return_details as $new_detail) {
                $new_products_id[] = $new_detail['id'];
            }

            // Init Data with old Parametre
            $old_products_id = [];
            foreach ($old_return_details as $key => $value) {
                $old_products_id[] = $value->id;

                 //check if detail has reservation_unit_id Or Null
                 if($value['reservation_unit_id'] !== null){
                    $unit = Unit::where('id', $value['reservation_unit_id'])->first();
                }else{
                    $product_unit_reservation_id = Product::with('unitReservation')
                    ->where('id', $value['product_id'])
                    ->first();

                    if($product_unit_reservation_id['unitReservation']){
                        $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                    }{
                        $unit = NULL;
                    }
                }

                if($value['reservation_unit_id'] !== null){
                    if ($current_ReservationReturn->status == "received") {
                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_ReservationReturn->warehouse_id)
                                ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_ReservationReturn->warehouse_id)
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

                    // Delete Detail
                    if (!in_array($old_products_id[$key], $new_products_id)) {
                        $ReservationItemReturn = ReservationItemReturn::findOrFail($value->id);
                        $ReservationItemReturn->delete();
                    }
                }

            }

            // Update Data with New request
            foreach ($new_return_details as $key => $product_detail) {
               
                $get_type_product = Product::where('id', $product_detail['product_id'])->first()->type;

                if($product_detail['no_unit'] !== 0 || $get_type_product == 'is_service'){

                    $unit_prod = Unit::where('id', $product_detail['reservation_unit_id'])->first();

                    if ($request['status'] == "received") {

                        if ($product_detail['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('product_id', $product_detail['product_id'])
                                ->where('product_variant_id', $product_detail['product_variant_id'])
                                ->first();

                            if ($unit_prod && $product_warehouse) {
                                if ($unit_prod->operator == '/') {
                                    $product_warehouse->qte += $product_detail['qte'] / $unit_prod->operator_value;
                                } else {
                                    $product_warehouse->qte += $product_detail['qte'] * $unit_prod->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('product_id', $product_detail['product_id'])
                                ->first();

                            if ($unit_prod && $product_warehouse) {
                                if ($unit_prod->operator == '/') {
                                    $product_warehouse->qte += $product_detail['qte'] / $unit_prod->operator_value;
                                } else {
                                    $product_warehouse->qte += $product_detail['qte'] * $unit_prod->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }

                    $reservationItem['reservation_return_id'] = $id;
                    $reservationItem['reservation_unit_id'] = $product_detail['reservation_unit_id'];
                    $reservationItem['qte'] = $product_detail['qte'];
                    $reservationItem['price'] = $product_detail['price'];
                    $reservationItem['tax_net'] = $product_detail['tax_percent'];
                    $reservationItem['tax_method'] = $product_detail['tax_method'];
                    $reservationItem['discount'] = $product_detail['discount'];
                    $reservationItem['discount_method'] = $product_detail['discount_Method'];
                    $reservationItem['product_id'] = $product_detail['product_id'];
                    $reservationItem['product_variant_id'] = $product_detail['product_variant_id'];
                    $reservationItem['total'] = $product_detail['subtotal'];
                    $reservationItem['imei_number'] = $product_detail['imei_number'];

                    if (!in_array($product_detail['id'], $old_products_id)) {
                        ReservationItemReturn::Create($reservationItem);
                    } else {
                        ReservationItemReturn::where('id', $product_detail['id'])->update($reservationItem);
                    }
                }

            }

            $due = $request['total_price'] - $current_ReservationReturn->paid_amount;
            if ($due === 0.0 || $due < 0.0) {
                $payment_status = 'paid';
            } else if ($due != $request['total_price']) {
                $payment_status = 'partial';
            } else if ($due == $request['total_price']) {
                $payment_status = 'unpaid';
            }

            $current_ReservationReturn->update([
                'date' => $request['date'],
                'notes' => $request['notes'],
                'status' => $request['status'],
                'tax_rate' => $request['tax_rate'],
                'tax_net' => $request['tax_net'],
                'discount' => $request['discount'],
                'shipping' => $request['shipping'],
                'total_price' => $request['total_price'],
                'payment_status' => $payment_status,
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Delete Reservation Return--------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', ReservationReturn::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_ReservationReturn = ReservationReturn::findOrFail($id);
            $old_return_details = ReservationItemReturn::where('reservation_return_id', $id)->get();

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === current_ReservationReturn->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_ReservationReturn);
            }

            foreach ($old_return_details as $key => $value) {

                 //check if detail has reservation_unit_id Or Null
                 if($value['reservation_unit_id'] !== null){
                    $unit = Unit::where('id', $value['reservation_unit_id'])->first();
                }else{
                    $product_unit_reservation_id = Product::with('unitReservation')
                    ->where('id', $value['product_id'])
                    ->first();

                    if($product_unit_reservation_id['unitReservation']){
                        $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                    }{
                        $unit = NULL;
                    }
                }

                if ($current_ReservationReturn->status == "received") {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_ReservationReturn->warehouse_id)
                            ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_ReservationReturn->warehouse_id)
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

            $current_ReservationReturn->details()->delete();
            $current_ReservationReturn->update([
                'deleted_at' => Carbon::now(),
            ]);

             // get all payments
             $payments = PaymentReservationReturn::where('reservation_return_id', $id)->get();

             foreach ($payments as $payment) {
 
                 $account = Account::find($payment->account_id);
 
                 if ($account) {
                     $account->update([
                         'balance' => $account->balance + $payment->montant,
                     ]);
                 }
 
             }

            PaymentReservationReturn::where('reservation_return_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', ReservationReturn::class);

        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $ReservationReturn_id) {

                $current_ReservationReturn = ReservationReturn::findOrFail($ReservationReturn_id);
                $old_return_details = ReservationItemReturn::where('reservation_return_id', $ReservationReturn_id)->get();
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === current_ReservationReturn->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_ReservationReturn);
                }

                foreach ($old_return_details as $key => $value) {

                    //check if detail has reservation_unit_id Or Null
                    if($value['reservation_unit_id'] !== null){
                       $unit = Unit::where('id', $value['reservation_unit_id'])->first();
                   }else{
                       $product_unit_reservation_id = Product::with('unitReservation')
                       ->where('id', $value['product_id'])
                       ->first();
                       
                       if($product_unit_reservation_id['unitReservation']){
                        $unit = Unit::where('id', $product_unit_reservation_id['unitReservation']->id)->first();
                    }{
                        $unit = NULL;
                    }
                   }
   
                   if ($current_ReservationReturn->status == "received") {
                       if ($value['product_variant_id'] !== null) {
                           $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_ReservationReturn->warehouse_id)
                               ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                           $product_warehouse = product_warehouse::where('deleted_at', '=', null)->where('warehouse_id', $current_ReservationReturn->warehouse_id)
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

                $current_ReservationReturn->details()->delete();
                $current_ReservationReturn->update([
                    'deleted_at' => Carbon::now(),
                ]);

                  // get all payments
                $payments = PaymentReservationReturn::where('reservation_return_id', $ReservationReturn_id)->get();

                foreach ($payments as $payment) {
    
                    $account = Account::find($payment->account_id);
    
                    if ($account) {
                        $account->update([
                            'balance' => $account->balance + $payment->montant,
                        ]);
                    }
    
                }
                PaymentReservationReturn::where('reservation_return_id', $ReservationReturn_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }



    //------------- GET Payments Reservation Return-----------\\

    public function Payment_Returns(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentReservationReturn::class);

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $ReservationReturn = ReservationReturn::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === ReservationReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $ReservationReturn);
        }

        $payments = PaymentReservationReturn::with('ReservationReturn')
            ->where('reservation_return_id', $id)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $ReservationReturn->total_price - $ReservationReturn->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);
    }

  

    //------------ rederence Order Of Reservation Return --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('reservation_returns')->latest('id')->first();

        if ($last) {
            $item = $last->red;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'RT_1111';
        }
        return $code;
    }

    //---------------- Get Details Reservation Return  -----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', ReservationReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Reservation_Return = ReservationReturn::with('reservation','details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === ReservationReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Reservation_Return);
        }

        $return_details['red'] = $Reservation_Return->red;
        $return_details['reservation_id'] = $Reservation_Return->reservation_id?$Reservation_Return['reservation']->id:NULL;
        $return_details['reservation_ref'] = $Reservation_Return['reservation']?$Reservation_Return['reservation']->red:'---';
        $return_details['date'] = $Reservation_Return->date;
        $return_details['note'] = $Reservation_Return->notes;
        $return_details['status'] = $Reservation_Return->status;
        $return_details['discount'] = $Reservation_Return->discount;
        $return_details['shipping'] = $Reservation_Return->shipping;
        $return_details['tax_rate'] = $Reservation_Return->tax_rate;
        $return_details['tax_net'] = $Reservation_Return->tax_net;
        $return_details['client_name'] = $Reservation_Return['client']->name;
        $return_details['client_phone'] = $Reservation_Return['client']->phone;
        $return_details['client_adr'] = $Reservation_Return['client']->adresse;
        $return_details['client_email'] = $Reservation_Return['client']->email;
        $return_details['client_tax'] = $Reservation_Return['client']->tax_number;
        $return_details['warehouse'] = $Reservation_Return['warehouse']->name;
        $return_details['total_price'] = number_format($Reservation_Return->total_price, 2, '.', '');
        $return_details['paid_amount'] = number_format($Reservation_Return->paid_amount, 2, '.', '');
        $return_details['due'] = number_format($return_details['total_price'] - $return_details['paid_amount'], 2, '.', '');
        $return_details['payment_status'] = $Reservation_Return->payment_status;

        foreach ($Reservation_Return['details'] as $detail) {

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
            'reservation_Return' => $return_details,
            'company' => $company,
        ]);
    }

    //---------------- Show Elements Reservation Return ---------------\\

    public function create(Request $request)
    {

        //

    }

    //---------------- edit ---------------\\

    public function edit(Request $request , $id)
    {

        //

    }

    public function create_sell_return(Request $request , $id)
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
        $Return_detail['reservation_ref'] = $ReservationReturn->red;
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

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $ReservationReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;
            $data['product_type'] = $detail['product']['type'];
            $data['qte'] = 0;
            $data['reservation_qte'] = $detail->qte;
            $data['product_id'] = $detail->product_id;
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


        return response()->json([
            'details' => $details,
            'reservation_return' => $Return_detail,
        ]);

    }

    //------------- Reservation Return PDF-----------\\

    public function Return_pdf(Request $request, $id)
    {

        $details = array();
        $helpers = new helpers();
        $Reservation_Return = ReservationReturn::with('reservation','details.product.unitReservation')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $return_details['client_name'] = $Reservation_Return['client']->name;
        $return_details['client_phone'] = $Reservation_Return['client']->phone;
        $return_details['client_adr'] = $Reservation_Return['client']->adresse;
        $return_details['client_email'] = $Reservation_Return['client']->email;
        $return_details['client_tax'] = $Reservation_Return['client']->tax_number;
        $return_details['tax_net'] = number_format($Reservation_Return->tax_net, 2, '.', '');
        $return_details['discount'] = number_format($Reservation_Return->discount, 2, '.', '');
        $return_details['shipping'] = number_format($Reservation_Return->shipping, 2, '.', '');
        $return_details['status'] = $Reservation_Return->status;
        $return_details['reservation_ref'] = $Reservation_Return['reservation']?$Reservation_Return['reservation']->red:'---';
        $return_details['red'] = $Reservation_Return->red;
        $return_details['date'] = $Reservation_Return->date;
        $return_details['total_price'] = number_format($Reservation_Return->total_price, 2, '.', '');
        $return_details['paid_amount'] = number_format($Reservation_Return->paid_amount, 2, '.', '');
        $return_details['due'] = number_format($return_details['total_price'] - $return_details['paid_amount'], 2, '.', '');
        $return_details['payment_status'] = $Reservation_Return->payment_status;

        $detail_id = 0;
        foreach ($Reservation_Return['details'] as $detail) {
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
                    ->where('id', $detail->product_variant_id)
                    ->first();
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
            $data['discount'] = $detail->discount;number_format($detail->discount, 2, '.', '');

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

        $Html = view('pdf.Reservations_Return_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'return_reservation' => $return_details,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('Reservations_Return.pdf');
    }

    //------------- Show Form Edit Reservation Return-----------\\

    public function edit_sell_return(Request $request, $id, $reservation_id)
    {

        $this->authorizeForUser($request->user('api'), 'update', ReservationReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $ReservationReturn = ReservationReturn::with('reservation','details.product.unitReservation')
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
        $Return_detail['reservation_id'] = $ReservationReturn->reservation_id?$ReservationReturn['reservation']->id:NULL;
        $Return_detail['reservation_ref'] = $ReservationReturn['reservation']?$ReservationReturn['reservation']->red:'---';
        $Return_detail['date'] = $ReservationReturn->date;
        $Return_detail['tax_rate'] = $ReservationReturn->tax_rate;
        $Return_detail['tax_net'] = $ReservationReturn->tax_net;
        $Return_detail['discount'] = $ReservationReturn->discount;
        $Return_detail['shipping'] = $ReservationReturn->shipping;
        $Return_detail['notes'] = $ReservationReturn->notes;
        $Return_detail['status'] = $ReservationReturn->status;

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

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $ReservationReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;

            $sell_detail = ReservationItem::where('reservation_id', $reservation_id)
            ->where('product_id', $detail->product_id)
            ->where('product_variant_id', $detail->product_variant_id)
            ->first();

            $data['reservation_qte'] = $sell_detail->qte;
            $data['product_type'] = $detail['product']['type'];
            $data['qte'] = $detail->qte;
            $data['product_id'] = $detail->product_id;
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

        return response()->json([
            'details' => $details,
            'reservation_return' => $Return_detail,
        ]);
    }



}
