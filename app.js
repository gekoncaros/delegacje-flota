const key = "delegacje-flota-data-v1";
const initial = { trips: [], expenses: [], mileage: 0 };
let data = JSON.parse(localStorage.getItem(key) || "null") || initial;
const money = new Intl.NumberFormat("pl-PL", { style: "currency", currency: "PLN" });

function save() { localStorage.setItem(key, JSON.stringify(data)); }
function esc(value = "") { const node = document.createElement("span"); node.textContent = value; return node.innerHTML; }
function showToast(message) { const el = document.querySelector("#toast"); el.textContent = message; el.classList.add("show"); setTimeout(() => el.classList.remove("show"), 2800); }
function render() {
  document.querySelector("#tripsCount").textContent = data.trips.length;
  document.querySelector("#distanceCount").textContent = `${new Intl.NumberFormat("pl-PL").format(data.mileage || 0)} km`;
  document.querySelector("#expenseCount").textContent = money.format(data.expenses.reduce((sum, item) => sum + item.amount, 0));
  document.querySelector("#mileage").value = data.mileage || "";
  const trips = document.querySelector("#tripsList");
  trips.className = data.trips.length ? "items" : "empty-state";
  trips.innerHTML = data.trips.length ? data.trips.map(item => `<div class="item"><span class="item-icon">✈</span><div><strong>${esc(item.destination)}</strong><small>${item.startDate} – ${item.endDate} · ${esc(item.transport)}</small></div><span class="pill">Zgłoszona</span></div>`).join("") : "Nie masz jeszcze zgłoszonych delegacji.";
  const expenses = document.querySelector("#expensesList");
  expenses.className = data.expenses.length ? "items" : "empty-state";
  expenses.innerHTML = data.expenses.length ? data.expenses.map(item => `<div class="item"><span class="item-icon">⌁</span><div><strong>${esc(item.category)}</strong><small>${item.date}${item.note ? ` · ${esc(item.note)}` : ""}</small></div><strong>${money.format(item.amount)}</strong></div>`).join("") : "Brak kosztów do rozliczenia.";
}
document.querySelectorAll("[data-open-modal]").forEach(button => button.addEventListener("click", () => document.querySelector(`#${button.dataset.openModal}`).showModal()));
document.querySelector("#tripForm").addEventListener("submit", event => { event.preventDefault(); const form = new FormData(event.currentTarget); data.trips.unshift(Object.fromEntries(form)); save(); render(); event.currentTarget.closest("dialog").close(); event.currentTarget.reset(); showToast("Delegacja została zapisana."); });
document.querySelector("#expenseForm").addEventListener("submit", event => { event.preventDefault(); const form = Object.fromEntries(new FormData(event.currentTarget)); form.amount = Number(form.amount); data.expenses.unshift(form); save(); render(); event.currentTarget.closest("dialog").close(); event.currentTarget.reset(); showToast("Koszt został dodany."); });
document.querySelector("#mileageForm").addEventListener("submit", event => { event.preventDefault(); data.mileage = Number(document.querySelector("#mileage").value); save(); render(); showToast("Stan licznika został zapisany."); });
if ("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js");
let installPrompt; window.addEventListener("beforeinstallprompt", event => { event.preventDefault(); installPrompt = event; document.querySelector("#installButton").classList.remove("hidden"); });
document.querySelector("#installButton").addEventListener("click", async () => { if (!installPrompt) return; installPrompt.prompt(); await installPrompt.userChoice; installPrompt = null; document.querySelector("#installButton").classList.add("hidden"); });
render();
