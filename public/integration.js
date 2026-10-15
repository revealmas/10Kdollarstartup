// Merge into the inspected MVP. Never include full generated results in its
// static assets, localStorage, hidden markup or pre-payment network responses.
export class ApplyPilot {
  constructor(endpoint = './api.php') { this.endpoint = endpoint; }
  async start() {
    const response = await fetch(`${this.endpoint}?action=bootstrap`, { cache: 'no-store', credentials: 'same-origin' });
    this.config = await response.json();
    if (!response.ok) throw new Error(this.config.error);
    return this.config;
  }
  async call(action, input) {
    if (!this.config) await this.start();
    const response = await fetch(`${this.endpoint}?action=${encodeURIComponent(action)}`, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.config.csrf },
      body: JSON.stringify(input)
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Please retry later.');
    return result;
  }
  async prepare(resume, jobDescription, consent, adult) {
    const result = await this.call('prepare', { resume, job_description: jobDescription, consent, adult });
    sessionStorage.setItem('applypilot_order', result.order_id); // opaque ID only, not CV or output
    return result;
  }
  acknowledgePreview(orderId) { return this.call('preview-seen', { order_id: orderId }); }
  async checkout(orderId, billingCountry, acceptTerms, acceptDelivery) {
    const result = await this.call('checkout', { order_id: orderId, billing_country: billingCountry,
      accept_terms: acceptTerms, accept_delivery: acceptDelivery });
    const url = new URL(result.url);
    if (url.protocol !== 'https:' || url.hostname !== 'checkout.stripe.com') throw new Error('Invalid payment destination.');
    window.location.assign(url.href);
  }
  status(orderId) { return this.call('status', { order_id: orderId }); }
  result(orderId) { return this.call('result', { order_id: orderId }); }
  async remove(orderId) {
    const result = await this.call('delete', { order_id: orderId });
    sessionStorage.removeItem('applypilot_order'); return result;
  }
}
export function packageText(bundle) {
  const r = bundle.analysis;
  return ['ApplyPilot digital application assistance', bundle.notice, 'Original CV text', bundle.original_cv,
    'Fit overview', r.summary, 'Evidence from your CV', ...r.strengths.map(x => `${x.quote}\n${x.reason}`),
    'Requirements to verify', ...r.gaps, ...(bundle.cover_letter_template?['Cover letter template — review and personalise', bundle.cover_letter_template]:[]),
    'Interview preparation', ...r.interview_questions, 'Application tips', ...r.tips].join('\n\n');
}
export function downloadText(bundle) {
  const url = URL.createObjectURL(new Blob([packageText(bundle)], {type:'text/plain;charset=utf-8'}));
  const link = document.createElement('a'); link.href = url; link.download = 'applypilot-application-review.txt';
  link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
}
