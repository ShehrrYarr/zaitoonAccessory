@extends('user_navbar')
@section('content')

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .card {
        border-radius: .65rem;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
    }

    .card-header {
        background: #f7f9fc;
        font-weight: 600;
    }

    .muted {
        color: #6b7280;
        font-size: .875rem;
    }

    .cursor-disabled {
        pointer-events: none;
        opacity: .6;
    }

    .overlay-blur {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
        background: rgba(255, 255, 255, .35);
        z-index: 9999;
    }

    .overlay-blur .box {
        background: #fff;
        padding: 16px 20px;
        border-radius: 10px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .15);
        font-weight: 600;
    }

    /* Sticky bottom bar */
    .action-bar {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: #fff;
        border-top: 1px solid #e5e7eb;
        padding: 12px 16px;
        box-shadow: 0 -4px 16px rgba(0, 0, 0, .04);
    }

    .action-bar .summary {
        font-weight: 600;
    }

    .table thead th {
        white-space: nowrap;
    }
</style>

<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        <div class="content-header row">
        </div>

        <div class="container-fluid">

            <h3 class="mb-3">Bulk Add Batches</h3>

            {{-- 1) Vendor --}}
            <div class="card mb-3">
                <div class="card-body">
                    <label class="mb-1">Vendor</label>
                    <select id="vendor_id" class="form-control">
                        <option value="">Select Vendor</option>
                        @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->mobile_no }})</option>
                        @endforeach
                    </select>
                    <div class="muted mt-1">Choose vendor to enable accessory selection.</div>
                </div>
            </div>

            {{-- 2) Accessories (stacked, with search) --}}
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span>Accessories</span>
                    <input id="searchBox" type="search" class="form-control form-control-sm"
                        placeholder="Search accessory, company, group..." style="max-width: 320px;">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Group</th>
                                    <th>Min Qty</th>
                                    <th style="width:110px;"></th>
                                </tr>
                            </thead>
                            <tbody id="accessoriesBody" class="cursor-disabled">
                                @foreach ($accessories as $a)
                                <tr data-accessory-id="{{ $a->id }}" data-name="{{ strtolower($a->name) }}"
                                    data-company="{{ strtolower(optional($a->company)->name) }}"
                                    data-group="{{ strtolower(optional($a->group)->name) }}">
                                    <td>{{ $a->name }}</td>
                                    <td>{{ optional($a->company)->name }}</td>
                                    <td>{{ optional($a->group)->name }}</td>
                                    <td>{{ $a->min_qty }}</td>
                                    <td>
                                        <button class="btn btn-primary btn-sm selectAccessory" disabled>Select</button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-2 muted">Click <em>Select</em> to add batch details via popup.</div>
                </div>
            </div>

            {{-- 3) Selected Batches (stacked under accessories) --}}
            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Selected Batches</span>
                    <div class="text-right">
                        <div class="muted">Items: <strong id="itemsCount">0</strong></div>
                        <div>Total (Qty × Purchase): <strong id="grandTotal">0.00</strong></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered mb-0" id="selectedTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Accessory</th>
                                    <th>Qty</th>
                                    <th>Purchase</th>
                                    <th>Selling</th>
                                    <th>Purchase Date</th>
                                    <th>Description</th>
                                    <th>Line Total</th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="7" class="text-right">Grand Total</th>
                                    <th id="grandTotalFoot">0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- 4) Sticky bottom action bar: Pay Amount + Submit --}}
                <div class="action-bar d-flex flex-wrap align-items-center justify-content-between">
                    <div class="summary">
                        <span class="mr-3">Items: <span id="itemsCountBar">0</span></span>
                        <span>Grand Total: <span id="grandTotalBar">0.00</span></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="mr-2">
                            <label class="mb-1">Pay Amount (for all)</label>
                            <input type="number" id="payAmount" class="form-control" step="0.01" min="0" value="0"
                                style="min-width: 220px;">
                        </div>
                        <button id="submitAllBtn" class="btn btn-success ml-2" disabled>Submit All</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Add details for a selected accessory --}}
        <div class="modal fade" id="batchModal" tabindex="-1" role="dialog" aria-labelledby="batchModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Batch — <span id="modalAccessoryName"></span></h5>
                        <button type="button" class="close" data-dismiss="modal"
                            aria-label="Close"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-2">
                            <label>Purchase Date</label>
                            <input type="date" class="form-control" id="m_purchase_date"
                                value="{{ now()->toDateString() }}">
                        </div>
                        <div class="form-group mb-2">
                            <label>Description (Optional)</label>
                            <input type="text" class="form-control" id="m_description" placeholder="Enter description">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-4">
                                <label>Quantity Purchased</label>
                                <input type="number" class="form-control" id="m_qty" min="1" value="1">
                            </div>
                            <div class="form-group col-4">
                                <label>Purchase Price (per unit)</label>
                                <input type="number" class="form-control" id="m_pprice" step="0.01" min="0" value="0"
                                    required>
                            </div>
                            <div class="form-group col-4">
                                <label>Selling Price (per unit)</label>
                                <input type="number" class="form-control" id="m_sprice" step="0.01" min="0" value="0"
                                    required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="modalAddBtn" type="button" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

{{-- Blur overlay --}}
<div class="overlay-blur" id="overlay">
    <div class="box">
        <div class="spinner-border mr-2" role="status" aria-hidden="true"></div>
        Storing… Please wait
    </div>
</div>



<script>
    (function() {

       

  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const vendorSel = document.getElementById('vendor_id');
  const accBody   = document.getElementById('accessoriesBody');
  const searchBox = document.getElementById('searchBox');
  const overlay   = document.getElementById('overlay');

  const selTable   = document.querySelector('#selectedTable tbody');
  const submitBtn  = document.getElementById('submitAllBtn');
  const itemsCount = document.getElementById('itemsCount');
  const gTotalTop  = document.getElementById('grandTotal');
  const gTotalFoot = document.getElementById('grandTotalFoot');

  // Sticky bar mirrors
  const itemsCountBar = document.getElementById('itemsCountBar');
  const gTotalBar     = document.getElementById('grandTotalBar');
  const payAmount     = document.getElementById('payAmount');

  // Modal elements
  const modalName   = document.getElementById('modalAccessoryName');
  const m_date      = document.getElementById('m_purchase_date');
  const m_desc      = document.getElementById('m_description');
  const m_qty       = document.getElementById('m_qty');
  const m_pprice    = document.getElementById('m_pprice');
  const m_sprice    = document.getElementById('m_sprice');
  const m_addBtn    = document.getElementById('modalAddBtn');

  let cart = []; // {accessory_id, accessory_name, qty_purchased, purchase_price, selling_price, purchase_date, description}
  let currentAccessory = { id: null, name: '' };

  function fmt(n){ return (Math.round((+n + Number.EPSILON) * 100)/100).toFixed(2); }

  function recalc() {
    let total = 0;
    cart.forEach(i => total += (i.qty_purchased * i.purchase_price));
    const totalFmt = fmt(total);
    gTotalTop.textContent  = totalFmt;
    gTotalFoot.textContent = totalFmt;
    gTotalBar.textContent  = totalFmt;

    itemsCount.textContent   = cart.length;
    itemsCountBar.textContent= cart.length;

    submitBtn.disabled = !(vendorSel.value && cart.length > 0);
  }

  function redraw() {
    selTable.innerHTML = '';
    cart.forEach((row, idx) => {
      const tr = document.createElement('tr');
      const lineTotal = row.qty_purchased * row.purchase_price;

      tr.innerHTML = `
        <td>${idx+1}</td>
        <td>${row.accessory_name}</td>
        <td>${row.qty_purchased}</td>
        <td>${fmt(row.purchase_price)}</td>
        <td>${fmt(row.selling_price)}</td>
        <td>${row.purchase_date}</td>
        <td>${row.description ?? ''}</td>
        <td>${fmt(lineTotal)}</td>
        <td><button class="btn btn-sm btn-danger removeRow" data-index="${idx}">Remove</button></td>
      `;
      selTable.appendChild(tr);
    });
    recalc();
  }

  // Enable accessories once vendor is chosen
  vendorSel.addEventListener('change', () => {
    const enabled = !!vendorSel.value;
    accBody.classList.toggle('cursor-disabled', !enabled);
    document.querySelectorAll('.selectAccessory').forEach(btn => btn.disabled = !enabled);
    recalc();
  });

  // Instant search
  searchBox.addEventListener('input', () => {
    const q = searchBox.value.trim().toLowerCase();
    accBody.querySelectorAll('tr').forEach(tr => {
      const hay = [tr.dataset.name, tr.dataset.company, tr.dataset.group].join(' ');
      tr.style.display = hay.includes(q) ? '' : 'none';
    });
  });

  // Select accessory → open modal
  accBody.addEventListener('click', (e) => {
    if(!e.target.classList.contains('selectAccessory')) return;
    if(!vendorSel.value) { alert('Select a vendor first.'); return; }

    const tr = e.target.closest('tr');
    currentAccessory.id   = +tr.getAttribute('data-accessory-id');
    currentAccessory.name = tr.querySelector('td').textContent.trim();

    // reset modal fields
    modalName.textContent = currentAccessory.name;
    m_date.value  = "{{ now()->toDateString() }}";
    m_desc.value  = '';
    m_qty.value   = 1;
    m_pprice.value= 0;
    m_sprice.value= 0;

    $('#batchModal').modal('show');
  });

  // Modal Add → push to cart
  m_addBtn.addEventListener('click', () => {
    const qty   = +m_qty.value || 0;
    const pp    = +m_pprice.value || 0;
    const sp    = +m_sprice.value || 0;
    const pdate = m_date.value;
    const desc  = m_desc.value || null;

    if(qty < 1) { alert('Quantity must be at least 1.'); return; }
    if(pp < 0 || sp < 0) { alert('Prices cannot be negative.'); return; }
    if(!pdate) { alert('Purchase date is required.'); return; }

    cart.push({
      accessory_id: currentAccessory.id,
      accessory_name: currentAccessory.name,
      qty_purchased: qty,
      purchase_price: pp,
      selling_price: sp,
      purchase_date: pdate,
      description: desc
    });

    $('#batchModal').modal('hide');
    redraw();
  });

  // Remove selected line
  document.getElementById('selectedTable').addEventListener('click', (e) => {
    if(!e.target.classList.contains('removeRow')) return;
    const idx = +e.target.getAttribute('data-index');
    cart.splice(idx, 1);
    redraw();
  });

  // Submit all (same endpoint)
  submitBtn.addEventListener('click', async () => {
    if(!vendorSel.value || cart.length === 0) return;

    overlay.style.display = 'flex';
    submitBtn.disabled = true;

    try {
      const res = await fetch(`{{ route('batches.bulk.store') }}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          vendor_id: vendorSel.value,
          pay_amount: +payAmount.value || 0,
          items: cart
        })
      });

      const data = await res.json().catch(() => ({}));

      if (res.status === 422) {
        const errs = data.errors || {};
        const msg = Object.keys(errs).map(k => `${k}: ${errs[k].join(', ')}`).join('\n');
        throw new Error(msg || 'Validation failed.');
      }
      if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);

      // Reset UI
      cart = [];
      payAmount.value = 0;
      redraw();
      vendorSel.value = '';
      accBody.classList.add('cursor-disabled');
      document.querySelectorAll('.selectAccessory').forEach(btn => btn.disabled = true);
      searchBox.value = '';

      alert('Batches stored successfully.');
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to store batches.');
    } finally {
      overlay.style.display = 'none';
      submitBtn.disabled = false;
    }
  });

})();

</script>

@endsection