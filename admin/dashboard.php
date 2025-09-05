<?php
require_once __DIR__.'/../includes/functions.php';
require_admin();
require_once __DIR__.'/../includes/head.php';
require_once __DIR__.'/../includes/navbar.php';
?>
<div class="row g-3">
  <div class="col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Total (scope)</div>
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
    </div></div>
  </div>
  <div class="col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted">Entries</div>
      <div class="fs-4 fw-bold" id="kpi_entries">--</div>
      <div class="text-muted mt-2">Distinct Refs: <span id="kpi_refs">--</span></div>
    </div></div>
  </div>
  <div class="col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted">Agent Declared</div>
      <div>Paid: <span class="fw-bold" id="kpi_paid">--</span></div>
      <div>Unpaid: <span class="fw-bold" id="kpi_unpaid">--</span></div>
    </div></div>
  </div>
  <div class="col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <a class="btn btn-outline-primary w-100" id="btn_export_refs">Export CSV (scope)</a>
      <small class="text-muted d-block mt-2">Grouped payment refs within scope</small>
    </div></div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">This Week — Sales Trend</h6>
        <canvas id="chart_week" height="120"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Awaiting Processing</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" id="tbl_awaiting">
            <thead><tr>
              <th>When</th><th>Agent</th><th>Ref</th><th>€xpected</th><th>%</th><th>Paid?</th><th>Linked</th><th>Entries</th>
            </tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <small class="text-muted">Agent-declared refs not yet closed.</small>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <h6 class="mb-3">Payment Refs (Grouped) — <span id="scope_tag">Week</span></h6>
    <div class="table-responsive">
      <table class="table table-sm align-middle" id="tbl_refs">
        <thead><tr><th>Ref</th><th>Total</th><th>Entries</th><th>First</th><th>Last</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
let scopeSel = $('#scope');
let chart;

// robust money formatter (handles null/NaN)
function money(n){
  n = +n || 0;
  return new Intl.NumberFormat().format(n.toFixed(2));
}

// define a robust JSON helper (THIS WAS MISSING)
$.ajaxSetup({ cache: false });
function ajaxGet(url, data, onOk){
  $.ajax({ url, data, method:'GET', dataType:'json' })
    .done(function(r){
      if(!r || r.ok!==true){ console.error('API error/Not OK:', r); alert((r && r.error)||'API error'); return; }
      onOk(r);
    })
    .fail(function(xhr){
      console.error('AJAX fail', xhr.status, (xhr.responseText||'').slice(0,200));
      alert('API error '+xhr.status);
    });
}

function loadKpis(){
  ajaxGet('api.php',{action:'kpis',scope:scopeSel.val()},function(r){
    $('#kpi_total').text(money(r.data.total_amount));
    $('#kpi_entries').text(r.data.entries);
    $('#kpi_refs').text(r.data.refs);
    $('#kpi_paid').text(r.data.declared_paid);
    $('#kpi_unpaid').text(r.data.declared_unpaid);
    $('#range_label').text(r.data.range[0]+' → '+r.data.range[1]);
    $('#scope_tag').text(scopeSel.find(':selected').text());
  });
}

function loadWeekChart(){
  ajaxGet('api.php',{action:'week_series'},function(r){
    const labels = r.data.map(x=>x.date);
    const data = r.data.map(x=>x.total);
    if(chart) chart.destroy();
    const ctx = document.getElementById('chart_week');
    chart = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Total', data, tension: .3 }] },
      options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });
  });
}

function loadAwaiting(){
  ajaxGet('api.php',{action:'awaiting'},function(r){
    const tb = $('#tbl_awaiting tbody'); tb.empty();
    r.data.forEach(row=>{
      tb.append(`<tr>
        <td>${row.created_at}</td>
        <td>${row.client_name}</td>
        <td>${row.invoice_no}</td>
        <td>${row.declared_amount ? money(parseFloat(row.declared_amount)) : '—'}</td>
        <td>${row.commission_pct ?? '—'}</td>
        <td>${row.declared_paid==1?'Yes':'No'}</td>
        <td>${money(parseFloat(row.linked_amount||0))}</td>
        <td>${row.entry_count}</td>
      </tr>`);
    });
  });
}

function loadGrouped(){
  ajaxGet('api.php',{action:'refs_grouped',scope:scopeSel.val()},function(r){
    const tb = $('#tbl_refs tbody'); tb.empty();
    r.data.forEach(x=>{
      tb.append(`<tr>
        <td>${x.invoice_no}</td>
        <td>${money(parseFloat(x.total_amount))}</td>
        <td>${x.entry_count}</td>
        <td>${x.first_seen}</td>
        <td>${x.last_seen}</td>
        <td><a class="btn btn-sm btn-outline-primary" href="ref.php?invoice_no=${encodeURIComponent(x.invoice_no)}">View</a></td>
      </tr>`);
    });
  });
}

$('#btn_export_refs').on('click', function(e){
  e.preventDefault();
  window.location = 'export.php?scope='+encodeURIComponent(scopeSel.val());
});

scopeSel.on('change', function(){ loadKpis(); loadGrouped(); });

$(function(){ loadKpis(); loadWeekChart(); loadAwaiting(); loadGrouped(); });
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
