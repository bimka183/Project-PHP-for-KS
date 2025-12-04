const ENDPOINT = 'server.php';
const REFRESH_MS = 3000;
let processes = [];
let sortState = { key: 'cpu', dir: 'desc', type: 'number' };
function parseNumber(v) {
  const n = Number(String(v).replace(',', '.').trim());
  return isNaN(n) ? 0 : n;
}
function compare(a, b, key, type, dir) {
  let va = a[key], vb = b[key];
  if (type === 'number') { va = parseNumber(va); vb = parseNumber(vb); }
  else { va = (va ?? '').toLowerCase(); vb = (vb ?? '').toLowerCase(); }
  let res = va < vb ? -1 : va > vb ? 1 : 0;
  return dir === 'asc' ? res : -res;
}
function render() {
  const tbody = document.getElementById('proc-body');
  tbody.innerHTML = '';
  processes.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="cpu" data-label="CPU Usage">${row.cpu ?? ''}</td>
      <td class="pid" data-label="PID">${row.pid ?? ''}</td>
      <td class="owner" data-label="Owner">${row.user ?? ''}</td>
      <td class="cmd" data-label="Command">${row.cmd ?? ''}</td>
    `;
    tbody.appendChild(tr);
  });
  document.querySelectorAll('th .sort-indicator').forEach(el => el.textContent = '');
  const th = document.querySelector(`th[data-key="${sortState.key}"] .sort-indicator`);
  if (th) th.textContent = sortState.dir === 'asc' ? '▲' : '▼';
}
async function fetchData() {
  try {
    const res = await fetch(ENDPOINT, { cache: 'no-store' });
    const data = await res.json();
    processes = (data.processes || []).map(r => ({
      cpu: r.cpu ?? r.CPU ?? '',
      pid: r.pid ?? r.Id ?? '',
      user: r.user ?? r.Owner ?? '',
      cmd: r.cmd ?? r.ProcessName ?? r.CommandLine ?? ''
    }));
    processes.sort((a,b)=>compare(a,b,sortState.key,sortState.type,sortState.dir));
    render();
    const ts = new Date((data.time || Date.now())*1000);
    document.getElementById('status').textContent = `Last update: ${ts.toLocaleString()}`;
  } catch (err) {
    document.getElementById('status').textContent = 'Error loading data';
    document.getElementById('status').classList.add('error');
  }
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('thead th').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.key, type = th.dataset.type;
      if (sortState.key === key) sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
      else { sortState.key = key; sortState.type = type; sortState.dir = 'asc'; }
      processes.sort((a,b)=>compare(a,b,sortState.key,sortState.type,sortState.dir));
      render();
    });
  });
  fetchData();
  setInterval(fetchData, REFRESH_MS);
});