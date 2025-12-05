<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccessoryBatch;
use App\Models\company;
use App\Models\group;
use App\Models\MasterPassword;
use App\Models\vendor;
use App\Models\Accessory;
use App\Models\Accounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;





class AccessoryBatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

  public function bulkCreate()
    {
        $vendors = Vendor::orderBy('name')->get(['id','name','mobile_no']);
        $accessories = Accessory::with(['company:id,name','group:id,name'])
            ->orderBy('name')
            ->get(['id','name','company_id','group_id','min_qty']);

        return view('batches.bulk', compact('vendors', 'accessories'));
    }
    


  public function index(Request $request)
{
    $vendors    = Vendor::all();          // existing (if needed elsewhere)
    $accessories= Accessory::all();       // existing (if needed elsewhere)
    $groups     = \App\Models\group::all();
    $companies  = \App\Models\company::all();

    $query = AccessoryBatch::with(['accessory', 'user', 'vendor']);

    // Date range (created_at)
    if ($request->filled('start_date') && $request->filled('end_date')) {
        $start = $request->input('start_date') . ' 00:00:00';
        $end   = $request->input('end_date')   . ' 23:59:59';
        $query->whereBetween('created_at', [$start, $end]);
    }

    // Filter by Group (via accessory)
    if ($request->filled('group_id')) {
        $groupId = (int) $request->input('group_id');
        $query->whereHas('accessory', function ($q) use ($groupId) {
            $q->where('group_id', $groupId);
        });
    }

    // Filter by Company (via accessory)
    if ($request->filled('company_id')) {
        $companyId = (int) $request->input('company_id');
        $query->whereHas('accessory', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });
    }

    $batches = $query->orderByDesc('id')->get();

    // Sum of total purchase price (filtered!)
    $totalPurchasePrice = $batches->sum(function ($batch) {
        return $batch->qty_purchased * $batch->purchase_price;
    });

    return view('batches.index', compact(
        'batches', 'vendors', 'accessories', 'totalPurchasePrice',
        'groups', 'companies'
    ));
}






    // public function store(Request $request)
    // {
    //     // dd($request->all());
    //     try {
    //         $validated = $request->validate([
    //             'accessory_id'    => 'required|exists:accessories,id',
    //             'vendor_id'       => 'required|exists:vendors,id',
    //             'qty_purchased'   => 'required|integer|min:1',
    //             'purchase_price'  => 'required|numeric|min:0',
    //             'purchase_date'   => 'required|date',
    //         ]);
    //         $validated['user_id'] = auth()->id(); 
    //         $validated['qty_remaining'] = $validated['qty_purchased'];
    
    //         // Generate a unique barcode, e.g.,
    //         $lastId = \App\Models\AccessoryBatch::max('id') ?? 0;
    //         $validated['barcode'] = str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
    
    //         \App\Models\AccessoryBatch::create($validated);
    
    //         return redirect()->back()->with('success', 'Batch Added Successfully.');
    
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         // Laravel will redirect back with validation errors automatically.
    //         throw $e;
    //     } catch (\Exception $e) {
    //         // Log the error for debugging
    //         \Log::error('Batch creation failed: ' . $e->getMessage());
    
    //         return redirect()->back()
    //             ->withInput()
    //             ->with('danger', 'An unexpected error occurred while adding the batch. Please try again.');
    //     }
    // }

   public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'accessory_id'    => 'required|exists:accessories,id',
            'vendor_id'       => 'required|exists:vendors,id',
            'qty_purchased'   => 'required|integer|min:1',
            'purchase_price'  => 'required|numeric|min:0',
            'selling_price'   => 'required|numeric|min:0',
            'purchase_date'   => 'required|date',
            'description'     => 'nullable|string',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['qty_remaining'] = $validated['qty_purchased'];

        // Generate a unique barcode
        $lastId = \App\Models\AccessoryBatch::max('id') ?? 0;
        $validated['barcode'] = str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);

        $batch = \App\Models\AccessoryBatch::create($validated);

        // Accounts logic
        $totalAmount = $validated['qty_purchased'] * $validated['purchase_price'];
        $payAmount = $request->input('pay_amount', 0);

        // Credit: you owe the vendor for the batch
        \App\Models\Accounts::create([
            'vendor_id'   => $validated['vendor_id'],
            'batch_id'    => $batch->id, // ✅ added
            'Credit'      => $totalAmount,
            'Debit'       => 0,
            'description' => "Batch Purchase: {$batch->barcode} ({$validated['qty_purchased']} x {$validated['purchase_price']})",
            'created_by'  => auth()->id(),
        ]);

        // Debit: if you paid any amount now
        if ($payAmount > 0) {
            sleep(1);
            \App\Models\Accounts::create([
                'vendor_id'   => $validated['vendor_id'],
                'batch_id'    => $batch->id, // ✅ added
                'Credit'      => 0,
                'Debit'       => $payAmount,
                'description' => "Payment for Batch: {$batch->barcode}",
                'created_by'  => auth()->id(),
            ]);
        }

        return redirect()->back()->with('success', 'Batch Added Successfully.');

    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Exception $e) {
        \Log::error('Batch creation failed: ' . $e->getMessage());
        return redirect()->back()
            ->withInput()
            ->with('danger', 'An unexpected error occurred while adding the batch. Please try again.');
    }
}




    public function barcodeInfo($id)
{
    $batch = \App\Models\AccessoryBatch::with(['accessory', 'user','vendor'])->findOrFail($id);

    return response()->json([
        'success'     => true,
        'batch'       => [
            'barcode'       => $batch->barcode,
            'accessory'     => $batch->accessory->name ?? '',
            'vendor'        => $batch->vendor->name ?? '',
            'qty_purchased' => $batch->qty_purchased,
            'qty_remaining' => $batch->qty_remaining,
            'purchase_price'=> $batch->purchase_price,
            'selling_price'=> $batch->selling_price,
            'purchase_date' => $batch->purchase_date,
            // You can add more fields if needed
        ],
        // This will generate a SVG barcode as HTML string
        'barcode_html' => \DNS1D::getBarcodeHTML($batch->barcode, 'C128'),
    ]);
}

  

    public function bulkStore(Request $request)
    {
        $request->validate([
            'vendor_id'               => 'required|exists:vendors,id',
            'pay_amount'              => 'nullable|numeric|min:0',
            'items'                   => 'required|array|min:1',
            'items.*.accessory_id'    => 'required|exists:accessories,id',
            'items.*.qty_purchased'   => 'required|integer|min:1',
            'items.*.purchase_price'  => 'required|numeric|min:0',
            'items.*.selling_price'   => 'required|numeric|min:0',
            'items.*.purchase_date'   => 'required|date',
            'items.*.description'     => 'nullable|string',
        ]);

        $vendorId   = (int) $request->vendor_id;
        $userId     = auth()->id();
        $items      = $request->items;
        $payAmount  = (float) ($request->pay_amount ?? 0);

        DB::beginTransaction();
        try {
            $totalCredit = 0;
            $batchCodes  = [];

            foreach ($items as $row) {
                $data = [
                    'accessory_id'   => (int) $row['accessory_id'],
                    'vendor_id'      => $vendorId,
                    'qty_purchased'  => (int) $row['qty_purchased'],
                    'qty_remaining'  => (int) $row['qty_purchased'],
                    'purchase_price' => (float) $row['purchase_price'],
                    'selling_price'  => (float) $row['selling_price'],
                    'purchase_date'  => $row['purchase_date'],
                    'description'    => $row['description'] ?? null,
                    'user_id'        => $userId,
                    'barcode'        => (string) Str::uuid(), // temp
                ];

                /** @var AccessoryBatch $batch */
                $batch = AccessoryBatch::create($data);
                $batch->barcode = str_pad($batch->id, 5, '0', STR_PAD_LEFT);
                $batch->save();

                $lineTotal   = $data['qty_purchased'] * $data['purchase_price'];
                $totalCredit += $lineTotal;
                $batchCodes[] = $batch->barcode;

                // Credit (you owe vendor for each batch)
                Accounts::create([
                    'vendor_id'   => $vendorId,
                    'Credit'      => $lineTotal,
                    'Debit'       => 0,
                    'description' => "Batch Purchase: {$batch->barcode} ({$data['qty_purchased']} × {$data['purchase_price']})",
                    'created_by'  => $userId,
                ]);
            }

            // Single Debit for the combined payment (if any)
            if ($payAmount > 0) {
                Accounts::create([
                    'vendor_id'   => $vendorId,
                    'Credit'      => 0,
                    'Debit'       => $payAmount,
                    'description' => 'Payment for Batches: ' . implode(', ', $batchCodes),
                    'created_by'  => $userId,
                ]);
            }

            DB::commit();

            return response()->json([
                'status'  => 'ok',
                'message' => 'Batches stored successfully',
                'totals'  => [
                    'credit' => $totalCredit,
                    'debit'  => $payAmount,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to store batches. Please try again.',
            ], 500);
        }
    }


  
}
