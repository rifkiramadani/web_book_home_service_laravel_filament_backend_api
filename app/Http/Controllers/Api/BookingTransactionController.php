<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\HomeService;
use Illuminate\Http\Request;
use App\Models\BookingTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\StoreBookingTransactionRequest;
use App\Http\Resources\Api\BookingTransactionApiResource;

class BookingTransactionController extends Controller
{
    //
    public function store(StoreBookingTransactionRequest $request) {
        //validasi request
        $validated = $request->validate();

        DB::beginTransaction();

        try {
            //handle file upload
            if($request->hasFile('proof')) {
                $path = $request->file('proof')->store('payment_proof', 'public');
                $validated['proof'] = $path;
            }

            // VALIDASI DATA SEBELUM DI STORE
            //ambil data array ids dari request
            $serviceids = $request->input('service_ids');

            //jika service tidak ada, maka tampilkan pesan error
            if(empty($serviceids)) {
                return response()->json(['message' => 'No Service Selected'], 400);
            }

            $services = HomeService::whereIn('id', $serviceids)->get(); //collection dari HomeService yang dipilih dari request array;

            //jika collection services dari request tidak ada maka tampilkan pesan error
            if($services->isEmpty()) {
                return response()->json(['message' => 'Invalid services'], 400);
            }

            //kalkulasikan total price, tax sehingga mendapatkan grand total
            $totalPrice = $services->sum('price');
            $tax = 0.11 * $totalPrice;
            $grandTotal = $totalPrice + $tax;

            //set schedule_at menjadi keesokan hari nya atau tommorow
            $validated['schedule_at'] = Carbon::tommorow()->toDateString(); //parse menjadi date string

            //set beberapa data transaction payment
            $validated['total_amount'] = $grandTotal;
            $validated['sub_total'] = $totalPrice;
            $validated['total_tax_amount'] = $tax;
            $validated['is_paid'] = false;
            $validated['booking_trx_id'] = BookingTransaction::generateUniqueTrxId();

            //JIKA SEMUA DATA SUDAH LOLOS VALIDASI MAKA DATA SIAP DI STORE
            $newBookingTransaction = BookingTransaction::create($validated); //STORE DATA
            DB::commit();

            //jika ada data $newBookingTransaction gagal di store maka tampilkan pesan error
            if(!$newBookingTransaction) {
                DB::rollback();
                return response()->json(['message' => 'booking transaction not created'], 500);
            }

            //simpan atau store untuk setiap data service pada table transaction details
            foreach($services as $service) {
                $newBookingTransaction->transactionDetails()->create([
                    'home_service_id' => $service->id,
                    'price' => $service->price,
                ]);
            }

            //setelah semua data di store atau di simpan maka kembalikan nilai tersebut agar bisa di tampilkan di halaman booking success/finished
            return new BookingTransactionApiResource($newBookingTransaction->load(['transactionDetails']));
            // return response()->json(['message' => 'booking transaction has been created'], 200);

        } catch (\ValidationException $e) {
            DB::rollback();
            return response()->json(['message' => 'an error occured', 'error' => $e->getMessage()], 500);
        }

    }

    public function booking_details(Request $request) {
        $validated = $request->validate([
            'email' => 'required|string|max:255',
            'booking_trx_id' => 'required|string|max:255',
        ]);

        $bookingDetail = BookingTransaction::where('email', $validated['email'])
            ->where('booking_trx_id', $validated['booking_trx_id'])
            ->with([
                'transactionDetails',
                'transactionDetails.homeService',
            ])
            ->first(); //ambil satu data yang ketemu pertama kali saja

        if(!$bookingDetail) {
            return response()->json(['message' => 'Booking Not Found'], 400);
        }

        return new BookingTransactionApiResource($bookingDetail);
    }
}
