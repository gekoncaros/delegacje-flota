(() => {
  const KEY='delegacjeFlotaDemoV4';
  const read=()=>{try{return JSON.parse(localStorage.getItem(KEY)||'{}')||{}}catch{return {}}};
  const daysUntil=date=>{if(!date)return null;return Math.ceil((new Date(date+'T23:59:59')-new Date())/86400000)};
  function buildTasks(){
    const s=read(),role=s.role||'employee',tasks=[];
    const delegations=Array.isArray(s.delegations)?s.delegations:[];
    const vehicles=Array.isArray(s.vehicles)?s.vehicles:[];
    const incidents=Array.isArray(s.incidents)?s.incidents:[];
    const pending=delegations.filter(d=>d.status==='pending').length;
    const approved=delegations.filter(d=>d.status==='approved').length;
    const started=delegations.filter(d=>d.status==='started').length;
    const finished=delegations.filter(d=>d.status==='finished').length;
    const overdue=vehicles.filter(v=>[v.inspectionDue,v.insuranceDue].some(x=>{const d=daysUntil(x);return d!==null&&d<0})).length;
    const dueSoon=vehicles.filter(v=>[v.inspectionDue,v.insuranceDue].some(x=>{const d=daysUntil(x);return d!==null&&d>=0&&d<=30})).length;
    const unavailable=vehicles.filter(v=>v.status==='service'||v.status==='unsafe').length;
    if(role==='manager'&&pending)tasks.push({title:'Delegacje do akceptacji',desc:'Wnioski oczekujące na decyzję przełożonego.',count:pending,kind:'warn',view:'approvals'});
    if(role==='employee'&&approved)tasks.push({title:'Delegacje gotowe do rozpoczęcia',desc:'Zatwierdzone wyjazdy czekają na start.',count:approved,kind:'warn',view:'delegations'});
    if(started)tasks.push({title:'Trwające delegacje',desc:'Wyjazdy wymagające zakończenia i rozliczenia.',count:started,kind:'warn',view:'delegations'});
    if((role==='accounting'||role==='superadmin')&&finished)tasks.push({title:'Rozliczenia do obsługi',desc:'Zakończone delegacje oczekujące na księgowość.',count:finished,kind:'warn',view:'profile'});
    if(overdue)tasks.push({title:'Dokumenty pojazdów po terminie',desc:'Przegląd lub ubezpieczenie wymaga pilnej reakcji.',count:overdue,kind:'urgent',view:'calendar'});
    if(dueSoon)tasks.push({title:'Terminy w ciągu 30 dni',desc:'Zbliżające się przeglądy lub ubezpieczenia.',count:dueSoon,kind:'warn',view:'calendar'});
    if(unavailable)tasks.push({title:'Pojazdy niedostępne',desc:'Auta w serwisie lub oznaczone jako niezdatne.',count:unavailable,kind:'urgent',view:'fleet'});
    if(incidents.length)tasks.push({title:'Zgłoszone usterki',desc:'Zgłoszenia wymagające weryfikacji przez flotę.',count:incidents.length,kind:'warn',view:'adminFleet'});
    return tasks;
  }
  function navigate(view){close();if(typeof window.go==='function')window.go(view);}
  function render(){
    const tasks=buildTasks();
    document.getElementById('taskOverlay')?.remove();
    const overlay=document.createElement('div');overlay.className='task-overlay';overlay.id='taskOverlay';
    overlay.innerHTML=`<section class="task-panel" role="dialog" aria-modal="true" aria-label="Centrum zadań"><div class="task-head"><div><small>Delegacje + Flota</small><h2>Centrum zadań</h2></div><button class="task-close" aria-label="Zamknij">×</button></div>${tasks.length?`<div class="task-list">${tasks.map((t,i)=>`<button class="task-item ${t.kind}" data-task="${i}"><span><strong>${t.title}</strong><small>${t.desc}</small></span><span class="task-count">${t.count}</span></button>`).join('')}</div>`:`<div class="task-empty">✓ Brak pilnych zadań. Wszystko jest obsłużone.</div>`}</section>`;
    document.body.appendChild(overlay);overlay.querySelector('.task-close').onclick=close;overlay.addEventListener('click',e=>{if(e.target===overlay)close()});overlay.querySelectorAll('[data-task]').forEach(b=>b.onclick=()=>navigate(tasks[Number(b.dataset.task)].view));
  }
  function close(){document.getElementById('taskOverlay')?.remove()}
  function refresh(){
    const tasks=buildTasks(),count=tasks.reduce((a,t)=>a+t.count,0);let btn=document.getElementById('taskLaunch');
    if(!btn){btn=document.createElement('button');btn.id='taskLaunch';btn.className='task-launch';btn.type='button';btn.setAttribute('aria-label','Centrum zadań');btn.onclick=render;document.body.appendChild(btn)}
    btn.innerHTML=`☑${count?`<b>${count}</b>`:''}`;
  }
  const nativeSetItem=localStorage.setItem.bind(localStorage);localStorage.setItem=(k,v)=>{nativeSetItem(k,v);if(k===KEY)setTimeout(refresh,0)};
  window.addEventListener('storage',e=>{if(e.key===KEY)refresh()});
  window.DelegacjeTasks={refresh,open:render};
  refresh();
})();