<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Order;
use App\OrderDetail;
use App\Payment;
use Mail;

class ShoppingCartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $products = $request->session()->get('shopping_list', []);
        $subtotal = collect($products)->reduce(function ($carry, $i) {
          return $carry + ($i['qty'] * $i['product']->buyPrice);
        }, 0);
        return view('cart.index', compact('products', 'subtotal'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (!$request->user()) {
            \Session::flash('status', 'You must login or register to continue with your order.');
            return redirect('/login');
        }
        $products = $request->session()->get('shopping_list', []);
        $subtotal = collect($products)->reduce(function ($carry, $i) {
          return $carry + ($i['qty'] * $i['product']->buyPrice);
        }, 0);
	$user = $request->user();
        return view('cart.create', compact('subtotal', 'user'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $shoppingList = $request->session()->get('shopping_list');
        if (count($shoppingList) == 0) {
            abort(403, 'You must have items in your shopping cart to checkout.');
        }

        $orderNumber = 1;

        while (Order::find($orderNumber)) {
          $orderNumber = mt_rand(1, 60000);
        }

        $amt = collect($shoppingList)->reduce(function ($carry, $i) {
          return $carry + ($i['qty'] * $i['product']->buyPrice);
        }, 0);

        if ($request->has('redeem_points') == true) {
          $amt -= $request->user()->customer->loyalty_points / 100;
          $request->user()->customer->loyalty_points = 0;
          $request->user()->customer->save();
          if ($amt < 0) {
            $amt = 0;
          }
        }

        $payment = new Payment();
        $payment->customerNumber = $request->user()->customer->customerNumber;
        $payment->checkNumber = $request->input('checkNumber');
        $payment->paymentDate = Carbon::now();
        $payment->amount = $amt;
        $payment->save();

        $order = new Order();
        $order->orderDate = Carbon::now();
        $order->status = Order::$STATUS_INPROCESS;
        $order->customerNumber = $request->user()->customer->customerNumber;
        $order->orderNumber = $orderNumber;
        $order->save();

        $order = Order::where('orderNumber', $orderNumber)
                        ->first();

        $counter = 1;

        foreach ($shoppingList as $p) {
          $orderDetail = new OrderDetail();
          $orderDetail->orderNumber = $orderNumber;
          $orderDetail->productCode = $p['product']->productCode;
          $orderDetail->quantityOrdered = $p['qty'];
          $orderDetail->priceEach = $p['product']->buyPrice;
          $orderDetail->orderLineNumber = $counter++;
          $orderDetail->save();

          $p['product']->quantityInStock = $p['product']->quantityInStock - $p['qty'];
          $p['product']->save();
        }

        if (!$request->has('redeem_points')) {
          $request->user()->customer->loyalty_points += intval($order->getTotal());
          $request->user()->customer->save();
        }

        Mail::send('emails.orderreceived', compact('user', 'order'), function ($m) use ($user) {
            $m->from('lugnutzcp@gmail.com', 'Lugnutz Computer Parts');

            $m->to($user->email, $user->name)->subject('Your Order has been received!');
        });

        $request->session()->put('shopping_list', []);

        \Session::flash('status', 'Your order has been received.');

        return redirect()->action('HomeController@index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
