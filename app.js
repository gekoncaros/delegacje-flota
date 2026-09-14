const key = "delegacje-flota-data-v2";
const initial = {
  trips: [], expenses: [], fuelings: [], incidents: [], notifications: [],
  vehicles: [{id: crypto.randomUUID(), make:"Skoda", model:"Octavia", registration:"DEMO 001", mileage:42150, status:"Dostępny"}]
};
let data = JSON.parse(localStorage.getItem(key) || "null") || initial;
for (const name of Object.keys(initial)) if (!Array.isArray(data[name])) data[name] = initial[name];
const money = new Intl.NumberFormat("pl-PL", {style:"currency", currency:"PLN"});
const number = new Intl.NumberFormat("pl-PL");

function save(){ localStorage.setItem(key, JSON.stringify(data)); }
function esc(value=""){ const node=document.createElement("span"); node.textContent=String(value); return node.innerHTML; }
function showToast(message){ const el=document.querySelector("#toast"); el.textContent=message; el.classList.add("show"); setTimeout(()=>el.classList.remove("show"),2500); }
function notify(message){ data.notifications.unshift({id:crypto.randomUUID(), message, at:new Date().toISOString()}); save(); render(); }
function tripStatusLabel(status){ return ({draft:"Zgłoszona",started:"W trasie",finished:"Zakończona"})[status] || status; }
function getPosition(){ return new Promise((resolve,reject)=>{ if(!navigator.geolocation) return reject(new Error("GPS niedostępny")); navigator.geolocation.getCurrentPosition(p=>resolve({lat:p.coords.latitude, lng:p.coords.longitude, accuracy:Math.round(p.coords.accuracy)}), reject, {enableHighAccuracy:true, timeout:10000, maximumAge:0}); }); }
function vehicleName(id){ const v=data.vehicles.find(x=>x.id===id); return v ? `${v.make} ${v.model} — ${v.registration}` : "Nieznany pojazd"; }
function fileMeta(file){ return file && file.name ? {name:file.name,size:file.size,type:file.type} : null; }

function renderSelects(){
  const tripOptions = `<option value="">Bez przypisania</option>` + data.trips.map(t=>`<option value="${t.id}">${esc(t.destination)} · ${esc(t.startDate)}</option>`).join("");
  document.querySelector("#expenseTrip").innerHTML=tripOptions;
  const vehicleOptions=data.vehicles.map(v=>`<option value="${v.id}">${esc(v.make)} ${esc(v.model)} — ${esc(v.registration)}</option>`).join("");
  document.querySelector("#fuelVehicle").innerHTML=vehicleOptions;
  document.querySelector("#incidentVehicle").innerHTML=vehicleOptions;
}

function render(){
  const finishedKm=data.trips.reduce((sum,t)=>sum+Math.max(0,(t.endOdometer||0)-(t.startOdometer||0)),0);
  const allCosts=[...data.expenses,...data.fuelings].reduce((sum,x)=>sum+Number(x.amount||0),0);
  document.querySelector("#tripsCount").textContent=data.trips.length;
  document.querySelector("#distanceCount").textContent=`${number.format(finishedKm)} km`;
  document.querySelector("#expenseCount").textContent=money.format(allCosts);
  document.querySelector("#incidentCount").textContent=data.incidents.filter(x=>x.status!=="closed").length;

  const trips=document.querySelector("#tripsList");
  trips.className=data.trips.length?"items":"empty-state";
  trips.innerHTML=data.trips.length?data.trips.map(t=>{
    const action=t.status==="draft"?`<button class="mini-button" data-trip-action="start" data-id="${t.id}">Start</button>`:t.status==="started"?`<button class="mini-button danger" data-trip-action="finish" data-id="${t.id}">Zakończ</button>`:"";
    const meta=t.status==="finished"?` · ${number.format(Math.max(0,(t.endOdometer||0)-(t.startOdometer||0)))} km`:"";
    return `<div class="item"><span class="item-icon">✈</span><div><strong>${esc(t.destination)}</strong><small>${esc(t.startDate)} – ${esc(t.endDate)} · ${esc(t.transport)}${meta}</small></div><span class="pill status-${t.status}">${tripStatusLabel(t.status)}</span>${action}</div>`;
  }).join(""):"Nie masz jeszcze delegacji.";

  const vehicles=document.querySelector("#vehiclesList");
  vehicles.innerHTML=data.vehicles.length?data.vehicles.map(v=>`<div class="item"><span class="item-icon">🚗</span><div><strong>${esc(v.make)} ${esc(v.model)}</strong><small>${esc(v.registration)} · ${number.format(v.mileage||0)} km</small></div><span class="pill">${esc(v.status||"Dostępny")}</span></div>`).join(""):"Brak pojazdów.";

  const expenses=document.querySelector("#expensesList");
  const combined=[...data.expenses.map(x=>({...x,type:"Koszt"})),...data.fuelings.map(x=>({...x,type:"Tankowanie",category:"Paliwo"}))].sort((a,b)=>(b.createdAt||"").localeCompare(a.createdAt||""));
  expenses.className=combined.length?"items":"empty-state";
  expenses.innerHTML=combined.length?combined.map(x=>`<div class="item"><span class="item-icon">${x.type==="Tankowanie"?"⛽":"⌁"}</span><div><strong>${esc(x.category)}</strong><small>${esc(x.date||x.createdAt?.slice(0,10)||"")}${x.note?` · ${esc(x.note)}`:""}${x.document?.name?` · 📎 ${esc(x.document.name)}`:""}</small></div><strong>${money.format(Number(x.amount||0))}</strong></div>`).join(""):"Brak kosztów.";

  const incidents=document.querySelector("#incidentsList");
  const open=data.incidents.filter(x=>x.status!=="closed");
  incidents.className=open.length?"items":"empty-state";
  incidents.innerHTML=open.length?open.map(x=>`<div class="item"><span class="item-icon">⚠</span><div><strong>${esc(x.category)}</strong><small>${esc(vehicleName(x.vehicleId))} · ${esc(x.description)}${x.unsafe?" · NIE JECHAĆ":""}</small></div></div>`).join(""):"Brak otwartych zgłoszeń.";

  const notifications=document.querySelector("#notificationsList");
  notifications.className=data.notifications.length?"items":"empty-state";
  notifications.innerHTML=data.notifications.length?data.notifications.map(n=>`<div class="item"><span class="item-icon">🔔</span><div><strong>${esc(n.message)}</strong><small>${new Date(n.at).toLocaleString("pl-PL")}</small></div></div>`).join(""):"Brak powiadomień.";
  const badge=document.querySelector("#notificationBadge"); badge.textContent=data.notifications.length; badge.classList.toggle("hidden",!data.notifications.length);
  renderSelects();
  bindTripActions();
}

function bindTripActions(){
  document.querySelectorAll("[data-trip-action]").forEach(btn=>btn.onclick=()=>{
    const trip=data.trips.find(t=>t.id===btn.dataset.id); if(!trip)return;
    const action=btn.dataset.tripAction;
    const form=document.querySelector("#tripActionForm");
    form.tripId.value=trip.id; form.action.value=action;
    document.querySelector("#tripActionTitle").textContent=`${action==="start"?"Rozpocznij":"Zakończ"}: ${trip.destination}`;
    document.querySelector("#tripActionSubmit").textContent=action==="start"?"Rozpocznij delegację":"Zakończ delegację";
    if(action==="finish" && trip.startOdometer) form.odometer.min=trip.startOdometer;
    document.querySelector("#tripActionModal").showModal();
  });
}

document.querySelectorAll("[data-open-modal]").forEach(b=>b.addEventListener("click",()=>document.querySelector(`#${b.dataset.openModal}`).showModal()));
document.querySelectorAll("[data-close]").forEach(b=>b.addEventListener("click",()=>b.closest("dialog").close()));
document.querySelector("#notificationsButton").addEventListener("click",()=>document.querySelector("#notificationsModal").showModal());
document.querySelector("#clearNotifications").addEventListener("click",()=>{data.notifications=[];save();render();});

document.querySelector("#tripForm").addEventListener("submit",e=>{e.preventDefault();const f=Object.fromEntries(new FormData(e.currentTarget));data.trips.unshift({...f,id:crypto.randomUUID(),status:"draft",createdAt:new Date().toISOString()});save();notify(`Utworzono delegację do: ${f.destination}`);e.currentTarget.closest("dialog").close();e.currentTarget.reset();showToast("Delegacja została zapisana.");});

document.querySelector("#tripActionForm").addEventListener("submit",async e=>{e.preventDefault();const f=new FormData(e.currentTarget);const trip=data.trips.find(t=>t.id===f.get("tripId"));if(!trip)return;const action=f.get("action");const odometer=Number(f.get("odometer"));if(action==="finish" && odometer < Number(trip.startOdometer||0)){showToast("Końcowy licznik nie może być niższy.");return;}let gps=null;if(f.get("useGps")){try{gps=await getPosition();}catch{showToast("Nie udało się pobrać GPS — zapisuję bez lokalizacji.");}}
  if(action==="start"){trip.status="started";trip.startedAt=new Date().toISOString();trip.startOdometer=odometer;trip.startGps=gps;notify(`Rozpoczęto delegację: ${trip.destination}`);}else{trip.status="finished";trip.finishedAt=new Date().toISOString();trip.endOdometer=odometer;trip.endGps=gps;notify(`Zakończono delegację: ${trip.destination}`);}save();render();e.currentTarget.closest("dialog").close();e.currentTarget.reset();showToast("Zapisano przebieg delegacji.");});

document.querySelector("#expenseForm").addEventListener("submit",e=>{e.preventDefault();const fd=new FormData(e.currentTarget);const file=e.currentTarget.document.files[0];data.expenses.unshift({id:crypto.randomUUID(),tripId:fd.get("tripId"),category:fd.get("category"),amount:Number(fd.get("amount")),date:fd.get("date"),note:fd.get("note"),document:fileMeta(file),createdAt:new Date().toISOString()});save();notify("Dodano koszt do rozliczenia");e.currentTarget.closest("dialog").close();e.currentTarget.reset();showToast("Koszt został dodany.");});
document.querySelector("#expenseForm").document.addEventListener("change",e=>{const f=e.target.files[0];document.querySelector("#documentInfo").textContent=f?`Wybrano: ${f.name} (${Math.round(f.size/1024)} KB)`:"";});

document.querySelector("#fuelForm").addEventListener("submit",e=>{e.preventDefault();const fd=new FormData(e.currentTarget);const vehicle=data.vehicles.find(v=>v.id===fd.get("vehicleId"));const mileage=Number(fd.get("odometer"));if(vehicle && mileage<Number(vehicle.mileage||0)){showToast("Nowy przebieg nie może być niższy.");return;}if(vehicle)vehicle.mileage=mileage;data.fuelings.unshift({id:crypto.randomUUID(),vehicleId:fd.get("vehicleId"),liters:Number(fd.get("liters")),amount:Number(fd.get("amount")),odometer:mileage,station:fd.get("station"),document:fileMeta(e.currentTarget.document.files[0]),createdAt:new Date().toISOString()});save();notify(`Dodano tankowanie: ${vehicleName(fd.get("vehicleId"))}`);e.currentTarget.closest("dialog").close();e.currentTarget.reset();showToast("Tankowanie zostało zapisane.");});

document.querySelector("#vehicleForm").addEventListener("submit",e=>{e.preventDefault();const f=Object.fromEntries(new FormData(e.currentTarget));data.vehicles.push({id:crypto.randomUUID(),make:f.make,model:f.model,registration:f.registration,mileage:Number(f.mileage),status:"Dostępny"});save();render();e.currentTarget.closest("dialog").close();e.currentTarget.reset();showToast("Pojazd został dodany.");});

document.querySelector("#incidentForm").addEventListener("submit",e=>{e.preventDefault();const fd=new FormData(e.currentTarget);const unsafe=fd.get("unsafe")==="on";const vehicle=data.vehicles.find(v=>v.id===fd.get("vehicleId"));if(vehicle && unsafe)vehicle.status="NIE UŻYWAĆ";data.incidents.unshift({id:crypto.randomUUID(),vehicleId:fd.get("vehicleId"),category:fd.get("category"),description:fd.get("description"),unsafe,status:"open",createdAt:new Date().toISOString()});save();notify(`Nowe zgłoszenie floty: ${fd.get("category")}`);e.currentTarget.closest("dialog").close();e.currentTarget.reset();showToast("Zgłoszenie zapisane.");});

if("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js");
let installPrompt;window.addEventListener("beforeinstallprompt",e=>{e.preventDefault();installPrompt=e;document.querySelector("#installButton").classList.remove("hidden");});
document.querySelector("#installButton").addEventListener("click",async()=>{if(!installPrompt)return;installPrompt.prompt();await installPrompt.userChoice;installPrompt=null;document.querySelector("#installButton").classList.add("hidden");});

render();