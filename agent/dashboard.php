<?php
require_once __DIR__ . '/../includes/functions.php';
require_agent();
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<style>
  /* Fixed-height scrollable tables */
  .scroll-fixed {
    max-height: 320px;
    overflow-y: auto;
  }

  /* Keep table headers visible while scrolling (supported in modern browsers) */
  .table thead th {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 1;
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
    <div class="d-flex align-items-center justify-content-between">
      <h6 class="mb-3">All My Payment Refs</h6>
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-secondary" id="btn_export_all_csv">CSV</button>
        <button class="btn btn-outline-secondary" id="btn_export_all_xls">Excel</button>
        <button class="btn btn-outline-secondary" id="btn_export_all_pdf">PDF</button>
      </div>
    </div>
    <div class="table-responsive scroll-fixed" id="wrap_all_refs">
      <table class="table table-sm align-middle" id="tbl_all_refs">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Total (Mine)</th>
            <th>Entries (Mine)</th>
            <th>Last</th>
            <th>Declared</th>
            <th>Queue %</th>
            <th>Final %</th>
            <th></th>
          </tr>
        </thead>
        <tbody></tbody>
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
<script>
  let scopeSel = $('#scope');
  let chart;
  let currentRef = null;
  let currentRefTotal = 0;

  function money(n) {
    n = +n || 0;
    return new Intl.NumberFormat().format(n.toFixed(2));
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
      .done(function(r) {
        if (!r || r.ok !== true) {
          alert((r && r.error) || 'API error');
          return;
        }
        onOk(r);
      })
      .fail(function(xhr) {
        console.error('AJAX fail', xhr.status, (xhr.responseText || '').slice(0, 200));
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
      .done(function(r) {
        if (!r || r.ok !== true) {
          alert((r && r.error) || 'API error');
          return;
        }
        onOk(r);
      })
      .fail(function(xhr) {
        console.error('AJAX fail', xhr.status, (xhr.responseText || '').slice(0, 200));
        alert('API error ' + xhr.status);
      });
  }

  /* KPIs & charts */
  function loadKpis() {
    ajaxGet('api.php', {
      action: 'kpis',
      scope: scopeSel.val()
    }, function(r) {
      $('#kpi_total').text(money(r.data.total_amount));
      $('#range_label').text(r.data.range[0] + ' → ' + r.data.range[1]);
    });
  }

  function loadWeekChart() {
    ajaxGet('api.php', {
      action: 'week_series'
    }, function(r) {
      const labels = r.data.map(x => x.date);
      const data = r.data.map(x => x.total);
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

  /* Tables */
  function loadQueue() {
    ajaxGet('api.php', {
      action: 'queue_list'
    }, function(r) {
      const tb = $('#tbl_queue tbody');
      tb.empty();
      r.data.forEach(x => {
        tb.append(`<tr>
        <td>${x.created_at}</td>
        <td>${x.invoice_no}</td>
        <td>${x.declared_amount!==null && x.declared_amount!=='' ? money(parseFloat(x.declared_amount)):'—'}</td>
        <td>${x.commission_pct ?? '—'}</td>
        <td>${+x.declared_paid===1?'Yes':'No'}</td>
        <td>${money(parseFloat(x.linked_amount||0))}</td>
        <td>${x.entry_count}</td>
      </tr>`);
      });
    });
  }

  function loadDiscovered() {
    ajaxGet('api.php', {
      action: 'discovered'
    }, function(r) {
      const tb = $('#tbl_discovered tbody');
      tb.empty();
      r.data.forEach(x => {
        tb.append(`<tr>
        <td>${x.invoice_no}</td>
        <td>${money(parseFloat(x.total_amount))}</td>
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

  function loadAllRefs() {
    ajaxGet('api.php', {
      action: 'all_refs'
    }, function(r) {
      const tb = $('#tbl_all_refs tbody');
      tb.empty();
      r.data.forEach(x => {
        tb.append(`<tr>
        <td>${x.invoice_no}</td>
        <td>${money(parseFloat(x.total_amount))}</td>
        <td>${x.entry_count}</td>
        <td>${x.last_seen ?? '—'}</td>
        <td>${x.declared_amount!==null && x.declared_amount!=='' ? money(parseFloat(x.declared_amount)):'—'}</td>
        <td>${x.aq_commission_pct ?? '—'}</td>
        <td>${x.final_commission_pct ?? '—'}</td>
        <td><button class="btn btn-sm btn-outline-primary viewref" data-ref="${x.invoice_no}">View</button></td>
      </tr>`);
      });
    });
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


  /* Commission quick set from Discovered */
  $(document).on('click', '.setpct', function() {
    const ref = $(this).data('ref');
    const pct = $(this).closest('td').find('.pct').val();
    ajaxPost('api.php?action=set_commission', {
      csrf: '<?= csrf_token() ?>',
      invoice_no: ref,
      commission_pct: pct
    }, function() {
      loadDiscovered(); // disappears immediately after saving %
      loadAllRefs(); // reflect final % in the all-refs table
      alert('Saved');
    });
  });

  /* Ref detail modal (Timestamp + Amount only) + Paid status UI */
  $(document).on('click', '.viewref', function() {
    const ref = $(this).data('ref');
    currentRef = ref;
    $('#refTitle').text('Ref: ' + ref);
    ajaxGet('api.php', {
      action: 'ref_entries',
      invoice_no: ref
    }, function(r) {
      const tb = $('#tbl_ref_entries tbody');
      tb.empty();
      let total = 0;
      r.data.forEach(x => {
        const amt = parseFloat(x.amount || 0);
        total += amt;
        tb.append(`<tr>
    <td>${x.timestamp}</td>
    <td>${money(amt)}</td>
    <td>${esc(x.receipt_msg)}</td>
  </tr>`);
      });


      // Use meta from API (total + saved status)
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
    if (v === 'partial') {
      $('#partial_wrap').slideDown(120);
    } else {
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
      status: status,
      partial_amount: partial
    }, function(resp) {
      // refresh the computed summary from server response (authoritative)
      currentRefTotal = parseFloat(resp.data.total || currentRefTotal);
      $('#paid_status').val(resp.data.status).trigger('change');
      if (resp.data.status === 'partial') $('#partial_amount').val(resp.data.partial);
      $('#paid_value').text(money(resp.data.partial));
      $('#balance_value').text(money(resp.data.balance));
      alert('Paid status saved');
    });
  });

  /* Exporters for All Refs */
  function tableToArray($table) {
    const rows = [];
    $table.find('thead tr, tbody tr').each(function() {
      const row = [];
      $(this).find('th,td').each(function() {
        row.push($(this).text().trim());
      });
      rows.push(row);
    });
    return rows;
  }

  function downloadBlob(filename, mime, content) {
    const blob = new Blob([content], {
      type: mime
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
      URL.revokeObjectURL(url);
      a.remove();
    }, 0);
  }

  $('#btn_export_all_csv').on('click', function() {
    const rows = tableToArray($('#tbl_all_refs'));
    const csv = rows.map(r => r.map(cell => {
      const needsQuote = /[",\n]/.test(cell);
      return needsQuote ? `"${cell.replace(/"/g,'""')}"` : cell;
    }).join(',')).join('\n');
    downloadBlob('all_refs.csv', 'text/csv;charset=utf-8', csv);
  });

  $('#btn_export_all_xls').on('click', function() {
    // Excel will happily open CSV; we just use .xls extension
    const rows = tableToArray($('#tbl_all_refs'));
    const csv = rows.map(r => r.map(cell => {
      const needsQuote = /[",\n]/.test(cell);
      return needsQuote ? `"${cell.replace(/"/g,'""')}"` : cell;
    }).join(',')).join('\n');
    downloadBlob('all_refs.xls', 'application/vnd.ms-excel', csv);
  });

  $('#btn_export_all_pdf').on('click', function() {
    // Simple print-to-PDF: open a new window with the table HTML
    const w = window.open('', '_blank');
    const html = `
<html><head>
  <title>All My Payment Refs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>table{font-size:12px} .table thead th{background:#f8f9fa;}</style>
</head><body class="p-3">
  <h5>All My Payment Refs</h5>
  <div class="table-responsive">
    ${document.getElementById('wrap_all_refs').innerHTML}
  </div>
</body></html>`;
    w.document.open();
    w.document.write(html);
    w.document.close();
    // print after the new document finishes loading
    w.addEventListener('load', () => w.print());
    w.document.open();
    w.document.write(html);
    w.document.close();
  });

  /* Add ref & other events */
  $('#formAdd').on('submit', function(e) {
    e.preventDefault();
    ajaxPost('api.php?action=add_ref', $(this).serialize(), function() {
      const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAdd'));
      m.hide();
      loadQueue();
      loadAllRefs();
      alert('Added');
    });
  });

  $('#btn_export_queue').on('click', function(e) {
    e.preventDefault();
    window.location = 'export.php';
  });
  scopeSel.on('change', function() {
    loadKpis();
  });

  /* Init */
  $(function() {
    loadKpis();
    loadWeekChart();
    loadQueue();
    loadDiscovered();
    loadAllRefs();
  });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>