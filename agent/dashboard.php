<?php
require_once __DIR__ . '/../includes/functions.php';
require_agent();
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<style>
  /* Fixed-height scrollable tables */
  .scroll-fixed {
    max-height: 320px;
    overflow-y: auto;
  }

  .table thead th {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 1;
  }

  .dt-control {
    cursor: pointer;
  }

  .badge-pill {
    border-radius: 50rem;
    padding: .35em .6em;
    font-weight: 600;
  }

  .note-wrap {
    min-width: 220px;
  }
</style>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted">My Total (scope)</div>
            <div class="fs-4 fw-bold" id="kpi_total">--</div>
          </div>
          <select id="scope" class="form-select form-select-sm" style="width:auto">
            <option value="today">Today</option>
            <option value="week" selected>Week</option>
            <option value="month">Month</option>
            <option value="year">Year</option>
          </select>
        </div>
        <div class="small text-muted mt-1" id="range_label"></div>
        <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#modalAdd">Add Payment Ref</button>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">My Week — Sales Trend</h6>
        <canvas id="chart_week" height="120"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">My Queue (Awaiting/Linked)</h6>
        <div class="table-responsive scroll-fixed">
          <table class="table table-sm align-middle" id="tbl_queue">
            <thead>
              <tr>
                <th>When</th>
                <th>Ref</th>
                <th>Expected</th>
                <th>%</th>
                <th>Paid?</th>
                <th>Linked (Mine)</th>
                <th>Entries (Mine)</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <a class="btn btn-outline-primary btn-sm mt-2" id="btn_export_queue">Export CSV (My Queue)</a>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Discovered Processed Refs (Not in My Queue)</h6>
        <div class="table-responsive scroll-fixed">
          <table class="table table-sm align-middle" id="tbl_discovered">
            <thead>
              <tr>
                <th>Ref</th>
                <th>Total (Mine)</th>
                <th>Entries (Mine)</th>
                <th>Last</th>
                <th>Set %</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h6 class="mb-0">All My Payment Refs</h6>
      <div class="d-flex align-items-center gap-2">
        <div class="input-group input-group-sm me-2">
          <span class="input-group-text">From</span>
          <input type="date" id="date_from" class="form-control">
          <span class="input-group-text">To</span>
          <input type="date" id="date_to" class="form-control">
          <button class="btn btn-secondary" id="btn_date_clear">Clear</button>
        </div>

        <!-- ⬇️ NEW: Notes keyword search -->
        <div class="input-group input-group-sm">
          <span class="input-group-text">Notes</span>
          <input type="text" id="notes_search" class="form-control" placeholder="Search notes…">
          <button class="btn btn-secondary" id="btn_notes_clear" type="button">Clear</button>
        </div>
      </div>
    </div>

    <div class="table-responsive" id="wrap_all_refs">
      <table class="table table-sm align-middle" id="tbl_all_refs" style="width:100%">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Total (Mine)</th>
            <th>Entries</th>
            <th>Last</th>
            <th>Declared</th>
            <th>Queue %</th>
            <th>Final %</th>
            <th>Commission</th>
            <th>Paid</th>
            <th class="note-wrap">Note</th>
            <th>Actions</th>
            <th style="display:none;">sort_id</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <th class="text-end">Totals (filtered):</th>
            <th class="text-end" id="ft_total">0.00</th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th class="text-end" id="ft_commission">0.00</th>
            <th></th>
            <th></th>
            <th></th>
            <th style="display:nonee;"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- Add Ref Modal -->
<div class="modal fade" id="modalAdd" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formAdd">
        <div class="modal-header">
          <h5 class="modal-title">Add Payment Ref</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="mb-2">
            <label class="form-label">Payment Ref</label>
            <input class="form-control" name="invoice_no" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Declared Amount (optional)</label>
            <input class="form-control" name="declared_amount" type="number" step="0.01">
          </div>
          <div class="mb-2">
            <label class="form-label">Commission % (optional)</label>
            <input class="form-control" name="commission_pct" type="number" step="0.001">
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="paid" name="declared_paid">
            <label class="form-check-label" for="paid">Mark as paid</label>
          </div>
          <div class="mb-2">
            <label class="form-label">Note</label>
            <input class="form-control" name="note" maxlength="500">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Ref Details + Paid Status Modal -->
<div class="modal fade" id="modalRef" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="refTitle">Ref Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="table-responsive scroll-fixed" style="max-height: 260px;">
              <table class="table table-sm align-middle" id="tbl_ref_entries">
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>Amount</th>
                    <th>Receipt</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="border rounded p-3">
              <div class="mb-2">
                <div class="text-muted small">Total Amount</div>
                <div class="fs-5 fw-bold" id="ref_total">0.00</div>
              </div>
              <div class="mb-2">
                <label class="form-label mb-1">Paid Status</label>
                <select id="paid_status" class="form-select form-select-sm">
                  <option value="not_paid">Not paid</option>
                  <option value="partial">Partially paid</option>
                  <option value="paid">Paid</option>
                </select>
              </div>
              <div id="partial_wrap" class="mb-2" style="display:none;">
                <label class="form-label mb-1">Partial amount</label>
                <input id="partial_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="0.00">
              </div>
              <div class="mb-2">
                <div class="d-flex justify-content-between"><span class="text-muted">Paid</span><span id="paid_value">0.00</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><span id="balance_value">0.00</span></div>
              </div>
              <div class="d-grid">
                <button class="btn btn-primary btn-sm" id="btn_save_paid">Save Status</button>
              </div>
              <small class="text-muted d-block mt-2" id="paid_hint"></small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- DataTables + Buttons -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script> <!-- ADD THIS -->


<script>
  let scopeSel = $('#scope');
  let chart, dt;
  let currentRef = null,
    currentRefTotal = 0;

  function money(n) {
    n = parseFloat(n || 0);
    return new Intl.NumberFormat().format(n.toFixed(2));
  }

  function pctOrDash(p) {
    return (p === null || p === undefined || p === '') ? '—' : (parseFloat(p).toFixed(3) + '%');
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      '\'': '&#39;'
    } [m]));
  }
  $.ajaxSetup({
    cache: false
  });

  function ajaxGet(url, data, onOk) {
    $.ajax({
        url,
        data,
        method: 'GET',
        dataType: 'json'
      })
      .done(r => {
        if (!r || r.ok !== true) {
          alert((r && r.error) || 'API error');
          return;
        }
        onOk(r);
      })
      .fail(xhr => {
        console.error('AJAX', xhr.status, (xhr.responseText || '').slice(0, 200));
        alert('API error ' + xhr.status);
      });
  }

  function ajaxPost(url, data, onOk) {
    $.ajax({
        url,
        data,
        method: 'POST',
        dataType: 'json'
      })
      .done(r => {
        if (!r || r.ok !== true) {
          alert((r && r.error) || 'API error');
          return;
        }
        onOk(r);
      })
      .fail(xhr => {
        console.error('AJAX', xhr.status, (xhr.responseText || '').slice(0, 200));
        alert('API error ' + xhr.status);
      });
  }

  /* KPIs & chart */
  function loadKpis() {
    ajaxGet('api.php', {
      action: 'kpis',
      scope: scopeSel.val()
    }, r => {
      $('#kpi_total').text(money(r.data.total_amount));
      $('#range_label').text(r.data.range[0] + ' → ' + r.data.range[1]);
    });
  }

  function loadWeekChart() {
    ajaxGet('api.php', {
      action: 'week_series'
    }, r => {
      const labels = r.data.map(x => x.date),
        data = r.data.map(x => x.total);
      if (chart) chart.destroy();
      chart = new Chart(document.getElementById('chart_week'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Total',
            data,
            tension: .3
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    });
  }

  /* Queue & Discovered (simple tables) */
  function loadQueue() {
    ajaxGet('api.php', {
      action: 'queue_list'
    }, r => {
      const tb = $('#tbl_queue tbody').empty();
      r.data.forEach(x => {
        tb.append(`<tr>
        <td>${x.created_at}</td>
        <td>${x.invoice_no}</td>
        <td>${x.declared_amount!==null && x.declared_amount!=='' ? money(x.declared_amount):'—'}</td>
        <td>${x.commission_pct ?? '—'}</td>
        <td>${+x.declared_paid===1?'Yes':'No'}</td>
        <td>${money(x.linked_amount||0)}</td>
        <td>${x.entry_count}</td>
      </tr>`);
      });
    });
  }

  function loadDiscovered() {
    ajaxGet('api.php', {
      action: 'discovered'
    }, r => {
      const tb = $('#tbl_discovered tbody').empty();
      r.data.forEach(x => {
        tb.append(`<tr>
        <td>${x.invoice_no}</td>
        <td>${money(x.total_amount)}</td>
        <td>${x.entry_count}</td>
        <td>${x.last_seen}</td>
        <td>
          <div class="input-group input-group-sm">
            <input class="form-control form-control-sm pct" data-ref="${x.invoice_no}" type="number" step="0.001" placeholder="%">
            <button class="btn btn-outline-primary btn-sm setpct" data-ref="${x.invoice_no}">Save</button>
          </div>
        </td>
      </tr>`);
      });
    });
  }
  $(document).on('click', '.setpct', function() {
    const ref = $(this).data('ref');
    const pct = $(this).closest('td').find('.pct').val();
    ajaxPost('api.php?action=set_commission', {
      csrf: '<?= csrf_token() ?>',
      invoice_no: ref,
      commission_pct: pct
    }, () => {
      loadDiscovered();
      loadAllRefsDT();
      alert('Saved');
    });
  });

  /* DataTables: All Refs */
  function paidBadge(status) {
    const s = (status || 'not_paid');
    if (s === 'paid') return '<span class="badge bg-success badge-pill">paid</span>';
    if (s === 'partial') return '<span class="badge bg-warning text-dark badge-pill">partial</span>';
    return '<span class="badge bg-danger badge-pill">unpaid</span>';
  }

  function computeCommission(row) {
    const total = parseFloat(row.total_amount || 0);
    const pct = (row.final_commission_pct ?? row.aq_commission_pct);
    if (pct === null || pct === undefined || pct === '') return null;
    return total * (parseFloat(pct) / 100);
  }

  function initAllRefsDT(data) {
    // Destroy previous
    if (dt) {
      dt.destroy();
      $('#tbl_all_refs tbody').empty();
    }

    // Build rows with computed fields & safe defaults
    const rows = (data || []).map(x => {
      const commission_val = computeCommission(x);
      const paid_status = x.paid_status || 'not_paid';
      return {
        invoice_no: x.invoice_no,
        total_amount: parseFloat(x.total_amount || 0),
        entry_count: parseInt(x.entry_count || 0),
        last_seen: x.last_seen || '',
        declared_amount: (x.declared_amount !== null && x.declared_amount !== '') ? parseFloat(x.declared_amount) : null,
        aq_commission_pct: (x.aq_commission_pct !== null && x.aq_commission_pct !== '') ? parseFloat(x.aq_commission_pct) : null,
        final_commission_pct: (x.final_commission_pct !== null && x.final_commission_pct !== '') ? parseFloat(x.final_commission_pct) : null,
        commission_value: commission_val,
        paid_status: paid_status,
        note: x.note || '',
        sort_id: (x.sort_id !== undefined && x.sort_id !== null) ? parseInt(x.sort_id) : null
      };
    });

    dt = $('#tbl_all_refs').DataTable({
      data: rows,
      columns: [{
          data: 'invoice_no'
        },
        {
          data: 'total_amount',
          className: 'text-end',
          render: d => money(d)
        },
        {
          data: 'entry_count',
          className: 'text-end'
        },
        {
          data: 'last_seen'
        },
        {
          data: 'declared_amount',
          className: 'text-end',
          render: d => (d === null || d === undefined) ? '—' : money(d)
        },
        {
          data: 'aq_commission_pct',
          render: pctOrDash,
          className: 'text-end'
        },
        {
          data: 'final_commission_pct',
          render: pctOrDash,
          className: 'text-end'
        },
        {
          data: 'commission_value',
          className: 'text-end',
          render: d => (d === null) ? '—' : money(d)
        },
        {
          data: 'paid_status',
          render: d => paidBadge(d),
          orderDataType: 'dom-text',
          type: 'string'
        },
        {
          data: 'note',
          className: 'note-wrap',
          render: function(data, type, row) {
            // For filtering/sorting, return plain note text
            if (type !== 'display') return data || '';
            // For display, render the input + button
            const val = esc(data || '');
            const ref = esc(row.invoice_no);
            return `
      <div class="input-group input-group-sm">
        <input class="form-control form-control-sm note-input" data-ref="${ref}" value="${val}" placeholder="Add note...">
        <button class="btn btn-outline-primary btn-sm save-note" data-ref="${ref}">Save</button>
      </div>`;
          }
        },
        {
          data: null,
          orderable: false,
          render: row => `<button class="btn btn-sm btn-outline-primary viewref" data-ref="${esc(row.invoice_no)}">View</button>`
        },
        {
          data: 'sort_id',
          visible: false
        } // hidden for default ordering if present
      ],
      order: (rows.length && rows[0].sort_id !== null) ? [
        [11, 'desc']
      ] : [
        [3, 'desc']
      ], // sort_id desc else last_seen desc
      paging: true,
      pageLength: 25,
      lengthMenu: [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, 'All']
      ],
      deferRender: true,
      autoWidth: false,
      scrollY: '380px',
      scrollCollapse: true,
      dom: 'Bfrtip',
      buttons: [{
          extend: 'csvHtml5',
          className: 'btn btn-secondary btn-sm',
          title: 'all_refs',
          footer: true,
          exportOptions: {
            modifier: {
              search: 'applied'
            }
          }
        },
        {
          extend: 'excelHtml5',
          className: 'btn btn-secondary btn-sm',
          title: 'all_refs',
          footer: true,
          exportOptions: {
            modifier: {
              search: 'applied'
            }
          }
        },
        {
          extend: 'pdfHtml5',
          className: 'btn btn-secondary btn-sm',
          title: 'all_refs',
          footer: true,
          exportOptions: {
            modifier: {
              search: 'applied'
            }
          },
          orientation: 'landscape',
          pageSize: 'A4'
        },
        {
          extend: 'print',
          className: 'btn btn-secondary btn-sm',
          title: 'All My Payment Refs',
          footer: true,
          exportOptions: {
            modifier: {
              search: 'applied'
            }
          }
        },
        {
          extend: 'colvis',
          className: 'btn btn-secondary btn-sm',
          text: 'Columns'
        }
      ],
      footerCallback: function(row, data, start, end, display) {
        const api = this.api();
        let totalSum = 0,
          commSum = 0;

        // Sum over all rows with the current search applied (not just current page)
        api.rows({
          search: 'applied'
        }).data().each(function(r) {
          // r is the original row object we fed to DataTables
          totalSum += parseFloat(r.total_amount || 0);
          commSum += parseFloat(r.commission_value || 0);
        });

        $('#ft_total').text(money(totalSum));
        $('#ft_commission').text(money(commSum));
      }

    });
    // Column index of Notes = 9 (0-based)
    $('#notes_search').on('input', function() {
      dt.column(9).search(this.value).draw();
    });
    $('#btn_notes_clear').on('click', function() {
      $('#notes_search').val('');
      dt.column(9).search('').draw();
    });


    // Custom date-range filter on Last (column index 3)
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
      if (settings.nTable.id !== 'tbl_all_refs') return true;
      const from = $('#date_from').val();
      const to = $('#date_to').val();
      const last = data[3]; // last_seen string
      if (!from && !to) return true;
      const d = last ? new Date(last.replace(' ', 'T')) : null;
      if (!d) return false;
      if (from && d < new Date(from)) return false;
      if (to && d > new Date(to + 'T23:59:59')) return false;
      return true;
    });

    $('#date_from,#date_to').off('change').on('change', () => dt.draw());
    $('#btn_date_clear').off('click').on('click', function() {
      $('#date_from').val('');
      $('#date_to').val('');
      dt.draw();
    });
  }

  // Load DataTable data
  function loadAllRefsDT() {
    ajaxGet('api.php', {
      action: 'all_refs'
    }, r => initAllRefsDT(r.data));
  }

  /* View modal (Timestamp + Amount + Receipt) + Paid Status */
  $(document).on('click', '.viewref', function() {
    const ref = $(this).data('ref');
    currentRef = ref;
    $('#refTitle').text('Ref: ' + ref);
    ajaxGet('api.php', {
      action: 'ref_entries',
      invoice_no: ref
    }, function(r) {
      const tb = $('#tbl_ref_entries tbody').empty();
      let total = 0;
      r.data.forEach(x => {
        const amt = parseFloat(x.amount || 0);
        total += amt;
        tb.append(`<tr><td>${x.timestamp}</td><td>${money(amt)}</td><td>${esc(x.receipt_msg)}</td></tr>`);
      });
      currentRefTotal = parseFloat(r.meta?.total_amount ?? total);
      $('#ref_total').text(money(currentRefTotal));

      const savedStatus = r.meta?.paid_status || 'not_paid';
      const savedPartial = r.meta?.partial_amount || 0;
      $('#paid_status').val(savedStatus).trigger('change');
      if (savedStatus === 'partial') $('#partial_amount').val(savedPartial);
      else $('#partial_amount').val('');
      updatePaidSummary();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRef')).show();
    });
  });

  $('#paid_status').on('change', function() {
    const v = $(this).val();
    if (v === 'partial') $('#partial_wrap').slideDown(120);
    else {
      $('#partial_wrap').slideUp(120);
      $('#partial_amount').val('');
    }
    updatePaidSummary();
  });
  $('#partial_amount').on('input', updatePaidSummary);

  function updatePaidSummary() {
    const mode = $('#paid_status').val();
    let paid = 0;
    if (mode === 'paid') paid = currentRefTotal;
    if (mode === 'partial') paid = Math.max(0, Math.min(currentRefTotal, parseFloat($('#partial_amount').val() || 0)));
    const bal = Math.max(0, currentRefTotal - paid);
    $('#paid_value').text(money(paid));
    $('#balance_value').text(money(bal));
    const hint = (mode === 'partial') ? 'Partial payment recorded; balance is auto-calculated.' :
      (mode === 'paid') ? 'Marked fully paid.' : 'Marked not paid.';
    $('#paid_hint').text(hint);
  }

  $('#btn_save_paid').on('click', function(e) {
    e.preventDefault();
    if (!currentRef) return;
    const status = $('#paid_status').val();
    const partial = status === 'partial' ? ($('#partial_amount').val() || '0') : '0';
    ajaxPost('api.php?action=set_paid_status', {
      csrf: '<?= csrf_token() ?>',
      invoice_no: currentRef,
      status,
      partial_amount: partial
    }, function(resp) {
      alert('Paid status saved');
      // Update badge in table if present
      if (dt) {
        const idx = dt.rows().indexes().toArray().find(i => dt.row(i).data().invoice_no === currentRef);
        if (idx !== undefined) {
          const row = dt.row(idx).data();
          row.paid_status = resp.data.status;
          dt.row(idx).data(row).draw(false);
        }
      }
    });
  });

  /* Save note (inline) */
  $(document).on('click', '.save-note', function() {
    const ref = $(this).data('ref');
    const $input = $(this).closest('.input-group').find('.note-input');
    const note = $input.val();
    ajaxPost('api.php?action=set_ref_note', {
      csrf: '<?= csrf_token() ?>',
      invoice_no: ref,
      note: note
    }, function() {
      // Optionally give feedback
      $input.addClass('is-valid');
      setTimeout(() => $input.removeClass('is-valid'), 800);
    });
  });

  /* Add Ref */
  $('#formAdd').on('submit', function(e) {
    e.preventDefault();
    ajaxPost('api.php?action=add_ref', $(this).serialize(), function() {
      const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAdd'));
      m.hide();
      loadQueue();
      loadAllRefsDT();
      alert('Added');
    });
  });

  /* Export old queue table */
  $('#btn_export_queue').on('click', function(e) {
    e.preventDefault();
    window.location = 'export.php';
  });

  /* KPI scope */
  scopeSel.on('change', function() {
    loadKpis();
  });

  /* Init */
  $(function() {
    loadKpis();
    loadWeekChart();
    loadQueue();
    loadDiscovered();
    loadAllRefsDT();
  });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>