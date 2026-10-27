'use strict';
const role = document.body.dataset.role;
const $ = id => document.getElementById(id);
const labels = {registered:'Apresentação cadastrada',running:'Em andamento · votação ainda fechada',open:'Votação aberta',closed:'Votação fechada',ended:'Apresentação encerrada'};
function el(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}
function resultText(stats) {
  return stats.average === null ? 'Ainda não há avaliações' : `Média ${stats.average} · ${stats.votes} avaliação(ões)`;
}
function badge(status) { return el('span', labels[status], `badge ${status}`); }
function form(action, values, label, danger = false) {
  const f = el('form'); f.method = 'post';
  f.append($('csrf-template').content.cloneNode(true));
  Object.entries({action,...values}).forEach(([name,value]) => {
    const i = el('input'); i.type = 'hidden'; i.name = name; i.value = value; f.append(i);
  });
  const b = el('button',label,danger?'danger small-button':'secondary small-button');
  f.append(b);
  f.addEventListener('submit',event => {
    if (danger && !confirm(`${label}? Esta ação afeta o acesso ou a apresentação em andamento.`)) event.preventDefault();
  });
  return f;
}
let currentPresentation = null;
function student(data) {
  const box = $('active-presentation');
  const p = data.active;
  box.replaceChildren();
  const vote = $('vote-form');
  if (!p) {
    box.append(el('h2','A próxima apresentação vem aí.'),el('p','Quando o administrador iniciar um seminário, ele aparecerá aqui.','muted'));
    vote.hidden = true; $('vote-message').textContent = ''; currentPresentation = null; return;
  }
  box.append(badge(p.status),el('h2',p.title,'presentation-title'),el('p',`Apresentador: ${p.real_name}`,'muted'));
  const id = String(p.id);
  if (currentPresentation !== id) $('grade').value = '';
  currentPresentation = id;
  $('vote-presentation').value = id;
  vote.hidden = p.status !== 'open' || p.own || p.voted;
  $('vote-message').textContent = p.own ? 'Esta é sua apresentação. A autoavaliação não é permitida.' : p.voted ? '✓ Sua avaliação já foi registrada. Obrigado por contribuir.' : p.status === 'open' ? '' : 'Aguarde a abertura da votação para enviar sua nota.';
}
function studentResults(data) {
  const box = $('student-results'); box.replaceChildren();
  if (!data.results.length) {box.append(el('p','Seus resultados aparecerão aqui após o encerramento das suas apresentações.','muted')); return;}
  data.results.forEach(p => {const row=el('article',undefined,'result-row'); row.append(el('h3',p.title),el('p',resultText(p.stats))); box.append(row);});
}
function adminPresentations(data) {
  const box = $('admin-presentations'); box.replaceChildren();
  if (!data.presentations.length) {box.append(el('p','Nenhuma apresentação cadastrada. Comece vinculando o código de um aluno.','muted'));return;}
  data.presentations.forEach(p => {
    const card = el('article',undefined,'presentation-row');
    card.append(badge(p.status),el('h3',p.title),el('p',`Apresentador: ${p.real_name}`),el('p',resultText(p.stats),'stat-line'));
    card.append(el('p',p.grades.length ? `Notas em ordem numérica: ${p.grades.map(g=>Number(g).toFixed(1).replace('.',',')).join(' · ')}` : 'Ainda não há avaliações','small muted'));
    const controls = el('div',undefined,'controls');
    const commands = p.status === 'registered' ? [['start','Iniciar com votação fechada']] : p.status === 'ended' ? [['reopen','Reabrir votação']] : p.status === 'running' ? [['open','Abrir votação'],['end','Encerrar apresentação']] : p.status === 'open' ? [['close','Fechar votação'],['end','Encerrar apresentação']] : [['reopen','Reabrir votação'],['end','Encerrar apresentação']];
    commands.forEach(([command,label])=>controls.append(form('control',{id:p.id,command},label,command==='end')));
    card.append(controls);box.append(card);
  });
}
function adminUsers(data) {
  const box = $('admin-users'); box.replaceChildren();
  if (!data.users.length) {box.append(el('p','Nenhuma conta criada ainda.','muted'));return;}
  data.users.forEach(u=>{
    const row=el('div',undefined,'account-row');
    const blocked=Number(u.blocked)===1;
    row.append(el('strong',u.username),el('span',blocked?'Bloqueada':'Ativa',blocked?'badge closed':'badge open'),form('block',{id:u.id,blocked:blocked?'0':'1'},blocked?'Desbloquear':'Bloquear',!blocked));
    box.append(row);
  });
}
let busy=false;
let lastAdminState='';
async function refresh() {
  if(busy) return;
  busy=true;
  try {
    const response=await fetch('index.php?api=1',{cache:'no-store',headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok) throw new Error(data.error || 'Não foi possível atualizar.');
    if(data.role !== role) { location.reload(); return; }
    if(role==='student') {student(data);studentResults(data);}
    else {
      const serialized=JSON.stringify(data);
      if(serialized!==lastAdminState) {adminPresentations(data);adminUsers(data);lastAdminState=serialized;}
    }
    $('connection-status').textContent='';
  } catch(error) {
    $('connection-status').textContent = `Atualização indisponível. ${error.message} Nova tentativa em até 10 segundos.`;
  } finally {busy=false;}
}
if(role==='admin'||role==='student') {
  refresh(); setInterval(refresh,10000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden) refresh();});
  window.addEventListener('focus',refresh);
}
if($('copy-code')) $('copy-code').addEventListener('click',async()=>{
  try {await navigator.clipboard.writeText($('private-code').value);$('copy-status').textContent='Código copiado.';}
  catch { $('private-code').select();$('copy-status').textContent='Selecione e copie o código manualmente.'; }
});
if($('vote-form')) $('vote-form').addEventListener('submit',event=>{
  const value=$('grade').value.trim().replace(',','.');
  if(!/^(?:[0-9](?:\.[0-9])?|10(?:\.0)?)$/.test(value)) {event.preventDefault();$('grade').setCustomValidity('Informe uma nota de 0 a 10 com no máximo uma casa decimal.');$('grade').reportValidity();return;}
  if(!confirm(`Confirmar nota ${value.replace('.',',')}? Após o envio, ela não poderá ser alterada.`)) event.preventDefault();
});
if($('grade')) $('grade').addEventListener('input',()=> $('grade').setCustomValidity(''));
