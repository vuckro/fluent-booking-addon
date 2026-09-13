const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
(async () => {
  const dom=new JSDOM('<html><div class="fcal_booking_form_wrap"><form><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div><div class="fluent_booking_payment_processor" style="display:none"></div><button type="submit" class="fcal_btn_submit">Payer</button></form></div></html>',{runScripts:'outside-only'});
  const w=dom.window,d=w.document;
  w.fbaGuestForms={2:{limit:5,nameMode:'required',emailMode:'hidden',price:true,unit:70,currency:'EUR',fields:[],tariffs:[{id:'adult',title:'Adulte',cents:7000},{id:'child',title:'Enfant',cents:5500}]}};
  w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
  const add=d.querySelector('.fba-add-guest');
  const processor=d.querySelector('.fluent_booking_payment_processor');
  const form=d.querySelector('form');
  form.addEventListener('submit',e=>{e.preventDefault();d.querySelector('.fcal_btn_submit').classList.add('fcal_btn_submitting');});
  form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  assert(add.disabled,'participants locked synchronously before any response');
  add.click();assert.equal(d.querySelectorAll('.fba-attached-guest').length,0);
  d.querySelector('.fcal_btn_submit').classList.remove('fcal_btn_submitting');
  await new Promise(resolve=>setTimeout(resolve,0));
  assert(!add.disabled,'native validation/request failure restores editing');
  form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  w.dispatchEvent(new w.CustomEvent('fluent_booking_payment_next_action_stripe',{detail:{form,response:{data:{
    data:{payment_args:{amount:7000,currency:'eur'}},intent:{amount:7000,currency:'eur'}
  }}}}));
  assert(add.disabled,'participants stay locked after verified response');
  assert(!d.querySelector('.fba-payment-recap').hidden,'frozen recap remains visible');
  assert.match(d.querySelector('.fba-stripe-total').textContent,/70/);
  d.querySelector('[data-fba-tariff]').dispatchEvent(new w.Event('change',{bubbles:true}));
  assert(add.disabled,'later change events cannot unlock the party');
  console.log('PASS Stripe checkout locks participant controls after the amount is frozen');
  w.dispatchEvent(new w.Event('pagehide'));w.close();
})().catch(error=>{console.error(error);process.exit(1);});
