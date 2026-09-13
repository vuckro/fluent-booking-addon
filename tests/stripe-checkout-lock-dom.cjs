const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
(async () => {
  const dom=new JSDOM('<html><div class="fcal_booking_form_wrap"><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div><div class="fluent_booking_payment_processor" style="display:none"></div></div></html>',{runScripts:'outside-only'});
  const w=dom.window,d=w.document;
  w.fbaGuestForms={2:{limit:5,nameMode:'required',emailMode:'hidden',price:true,unit:70,currency:'EUR',fields:[],tariffs:[{id:'adult',title:'Adulte',cents:7000},{id:'child',title:'Enfant',cents:5500}]}};
  w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
  const add=d.querySelector('.fba-add-guest');
  const processor=d.querySelector('.fluent_booking_payment_processor');
  processor.style.display='block';processor.append(d.createElement('iframe'));
  await Promise.resolve();
  assert(add.disabled,'participants are locked once Stripe mounts');
  assert(d.querySelector('.fba-payment-recap').hidden,'mutable participant recap is hidden during Stripe checkout');
  assert.match(d.querySelector('.fba-payment-locked-notice').textContent,/paiement a été préparé/);
  console.log('PASS Stripe checkout locks participant controls after the amount is frozen');
  w.dispatchEvent(new w.Event('pagehide'));w.close();
})().catch(error=>{console.error(error);process.exit(1);});
