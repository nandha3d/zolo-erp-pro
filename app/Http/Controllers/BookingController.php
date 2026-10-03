<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Table;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function calendar(Request $request)
    {
        $bookings = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->select('bookings.*', 'customers.name as customer_name', 'customers.phone_number')
            ->latest()
            ->paginate(15);

        $warehouses = Warehouse::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $tables = Table::where('is_active', true)->get();

        return view('backend.bookings.calendar', compact('bookings', 'warehouses', 'customers', 'tables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
            'customer_id' => 'required',
            'booking_date' => 'required|date',
            'time_slot' => 'required|string',
        ]);

        $bNo = 'BKG-' . date('Ymd') . '-' . rand(100, 999);

        DB::table('bookings')->insert([
            'booking_no' => $bNo,
            'warehouse_id' => $request->warehouse_id,
            'customer_id' => $request->customer_id,
            'booking_date' => $request->booking_date,
            'time_slot' => $request->time_slot,
            'table_id' => $request->table_id,
            'service_type' => $request->service_type ?? 'General Reservation',
            'notes' => $request->notes,
            'status' => 'Confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', "Booking {$bNo} confirmed successfully.");
    }
}
