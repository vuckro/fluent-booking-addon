const {JSDOM,VirtualConsole}=require('jsdom'),fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const wp=process.env.WAASKIT_WP_PATH;
if (!wp || !process.env.FBA_PAGE_HTML || !process.env.FBA_SLOTS_JSON) throw Error('Set WAASKIT_WP_PATH, FBA_PAGE_HTML and FBA_SLOTS_JSON; uses local read-only fixtures for the Adulte 70 / Enfant 55 scenario.');
const nonparticipating=process.env.FBA_NONPARTICIPATING==='1';
const allAdult=process.env.FBA_ALL_ADULT==='1';
const expected=nonparticipating?5500:(allAdult?14000:12500);
const amountPattern=new RegExp(String(expected/100));
const html=fs.readFileSync(process.env.FBA_PAGE_HTML,'utf8');
let reloads=0;
const virtualConsole=new VirtualConsole();
virtualConsole.on('jsdomError',error=>{if(error.message==='Not implemented: navigation (except hash changes)')reloads++;else throw error;});
const dom=new JSDOM(html,{virtualConsole,url:'http://localhost:10038/?fluent-booking=calendar&host=waaskit&event=30min-1',runScripts:'outside-only',pretendToBeVisual:true});
const w=dom.window;w.matchMedia=()=>({matches:false,addEventListener(){},removeEventListener(){}});w.ResizeObserver=class {observe(){}disconnect(){}};w.HTMLElement.prototype.scrollIntoView=function(){};
const response=fs.readFileSync(process.env.FBA_SLOTS_JSON,'utf8');
w.fetch=async()=>({ok:true,json:async()=>JSON.parse(response),text:async()=>response});
let pending, stripeMounts=0;
w.XMLHttpRequest=class {open(method,url){this.method=method;this.url=url;}setRequestHeader(){}getAllResponseHeaders(){return 'content-type: application/json';}send(body){
 if(this.method !== 'GET') {w.testPostedBody=body;pending=this;return;}
 this.status=200;this.response=JSON.parse(response);this.responseText=response;this.readyState=4;setTimeout(()=>{this.onload?.();this.onreadystatechange?.();},0);
}addEventListener(type,fn){this['on'+type]=fn;}};
w.Stripe=()=>({elements:()=>({create:()=>({mount:()=>{stripeMounts++;},on:()=>{}})})});

for(const s of w.document.querySelectorAll('script:not([src])'))w.eval(s.textContent);
if (nonparticipating) w.fbaGuestForms[2].allowNonparticipating=true;
w.eval(fs.readFileSync(path.join(wp,'wp-content/plugins/fluent-booking/assets/public/js/app.js'),'utf8'));
w.eval(fs.readFileSync(path.join(wp,'wp-content/plugins/fluent-booking/assets/public/js/stripe-checkout.js'),'utf8'));
w.eval(fs.readFileSync(path.join(root,'assets/public/guests.js'),'utf8'));
setTimeout(async()=>{
 w.document.querySelector('.day-enabled')?.click();await new Promise(r=>setTimeout(r,50));
 w.document.querySelector('.fcal_spot_name')?.click();await new Promise(r=>setTimeout(r,50));
 w.document.querySelector('.fcal_spot_confirm')?.click();await new Promise(r=>setTimeout(r,50));
 const assert=require('assert'),d=w.document;
 const style=d.createElement('style');style.textContent=fs.readFileSync(path.join(root,'assets/public/guests.css'),'utf8');d.head.append(style);
 assert(d.querySelector('#fcalInputIDfba_extra_2').closest('.fcal_form_item').hidden);
 assert.equal(w.getComputedStyle(d.querySelector('.fcal_payment_items')).display,'none');

 assert(!d.querySelector('.fba-add-guest').closest('label'),'custom guests are outside native payment label');

 if (nonparticipating) d.querySelector('.fba-holder-participates').click();
 else d.querySelector('.fba-add-guest').click();await new Promise(r=>setTimeout(r,30));
 const guest=d.querySelector('.fba-attached-guest');guest.querySelector('[data-fba-identity=name]').value='Camille';
 const choice=guest.querySelector('[data-fba-tariff]');choice.selectedIndex=allAdult?0:1;choice.dispatchEvent(new w.Event('input',{bubbles:true}));await new Promise(r=>setTimeout(r,30));
 assert.match(d.querySelector('.fba-guest-summary').textContent,amountPattern);
 assert.match(d.querySelector('.fba-sidebar-total').textContent,amountPattern);
 assert.equal(JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value).guests[0].name,'Camille');
 d.querySelector('.fcal_back button').click();await new Promise(r=>setTimeout(r,30));
 d.querySelector('.fcal_spot_name').click();await new Promise(r=>setTimeout(r,30));d.querySelector('.fcal_spot_confirm').click();await new Promise(r=>setTimeout(r,30));
 assert.equal(d.querySelectorAll('.fba-add-guest').length,1);assert.equal(d.querySelectorAll('.fba-attached-guest').length,1);
 assert.equal(d.querySelector('[data-fba-identity=name]').value,'Camille');assert.match(d.querySelector('.fba-guest-summary').textContent,amountPattern);
 const name=d.querySelector('#fcalInputIDname'); name.value='Recette';name.dispatchEvent(new w.Event('input',{bubbles:true}));
 const email=d.querySelector('.fcal_booking_form_wrap input[type=email]:not([data-fba-identity])'); email.value='test@example.invalid';email.dispatchEvent(new w.Event('input',{bubbles:true}));
 d.querySelectorAll('[data-fba-answer]').forEach(input => {input.value='8';input.dispatchEvent(new w.Event('input',{bubbles:true}));});
 d.querySelector('.fcal_btn_submit').click();await new Promise(r=>setTimeout(r,30));
 assert(pending, 'native request is pending');
 assert(d.querySelector('.fba-add-guest').disabled,'locked before Stripe response');
 const frozen=d.querySelector('#fcalInputIDfba_extra_2').value;
 d.querySelector('.fba-add-guest').click();
 d.querySelector('[data-fba-tariff]').dispatchEvent(new w.Event('input',{bubbles:true}));
 assert.equal(d.querySelector('#fcalInputIDfba_extra_2').value,frozen,'request payload stays frozen');
 const amount=expected;
 const actual=process.env.FBA_MISMATCH==='1'?7000:amount;
 const result={success:true,data:{actionName:'custom',nextAction:'stripe',status:'success',data:{payment_method:'stripe',payment_args:{amount:actual,currency:'eur',public_key:'pk_test_fixture'}},intent:{amount:actual,currency:'eur',client_secret:'pi_fixture_secret'}}};
 pending.status=200;pending.response=result;pending.responseText=JSON.stringify(result);pending.readyState=4;pending.onload?.();pending.onreadystatechange?.();
 await new Promise(r=>setTimeout(r,50));
 if(process.env.FBA_MISMATCH==='1') {
   assert.equal(stripeMounts,0,'Stripe never mounts on a mismatched server amount');
   assert.match(d.querySelector('.fba-payment-locked-notice').textContent,/ne correspond pas/);
 } else {
   assert.equal(stripeMounts,1,'actual native Stripe handler mounted exactly once');
   assert.match(d.querySelector('.fba-stripe-total').textContent,amountPattern);
   assert.equal(w.getComputedStyle(d.querySelector('.fluent_booking_payment_processor > .label')).display,'none','stale native catalogue label replaced');
   assert(!d.querySelector('.fba-payment-recap').hidden,'frozen recap stays visible');
   assert(d.querySelector('.fba-add-guest').disabled,'controls remain locked after response');
 }

 assert(w.testPostedBody,'native form submitted to local mock');
 const submitted=w.testPostedBody.get('fba_extra_2');assert(submitted,'guest payload reaches native submission');
 const payload=JSON.parse(submitted);if(nonparticipating) assert.equal(payload.holder_participates,false);assert.equal(payload.guests[0].name,'Camille');assert.equal(payload.guests[0].tariff,w.fbaGuestForms[2].tariffs[allAdult?0:1].id);
 if(process.env.FBA_RESTART==='1') {
   w.fetch=()=>{throw Error('Restart must not call an API');};
   d.querySelector('.fba-restart-checkout').click();
   assert.equal(reloads,1,'restart reloads the page');
   d.querySelector('.fcal_back button').click();
   assert.equal(reloads,2,'native back reloads instead of reopening stale checkout');
   assert.equal(w.sessionStorage.length,0,'no personal draft or Stripe secret stored');
   assert(!d.querySelector('.fba-edit-checkout'),'old retry action removed');
 }
 console.log('PASS native Svelte + Stripe checkout: pending lock, response consistency, frozen recap, no network payment');
 w.dispatchEvent(new w.Event('pagehide'));w.close();
},200);
