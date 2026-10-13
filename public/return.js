import {ApplyPilot, packageText, downloadText} from './integration.js';
import {downloadDocx} from './docx.js';
const api = new ApplyPilot();
const status = document.getElementById('status');
const retry = document.getElementById('retry');
const download = document.getElementById('download');
const id = sessionStorage.getItem('applypilot_order');
let bundle;
async function check() {
  retry.disabled = true;
  try {
    const cfg = await api.start();
    if (cfg.support_email) document.getElementById('support').textContent = `Support and refunds: ${cfg.support_email}. Provide your Stripe receipt; do not email your CV.`;
    if (!id) throw new Error('Open the browser used for your preview. If access is lost, contact support with your Stripe receipt.');
    const state = await api.status(id);
    if (!state.paid) { status.textContent = `Payment state: ${state.state}. A return from checkout alone does not confirm payment. If you paid, wait briefly and check again.`; return; }
    bundle = (await api.result(id)).package;
    status.textContent = 'Payment verified. Your package is ready.';
    const view = document.getElementById('preview'); view.textContent = packageText(bundle); view.hidden = false;
    download.hidden = false;
    document.getElementById('docx').hidden = false; document.getElementById('pdf').hidden = false;
  } catch(error) { status.textContent = error.message; }
  finally { retry.disabled = false; }
}
retry.addEventListener('click', check); download.addEventListener('click', () => downloadText(bundle));
document.getElementById('docx').addEventListener('click',()=>{if(bundle)downloadDocx(bundle);});
document.getElementById('pdf').addEventListener('click',()=>{if(bundle)window.print();});
check();
