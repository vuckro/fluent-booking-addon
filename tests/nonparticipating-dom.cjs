const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
const markup='<div class="fcal_booking_form_wrap"><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div><div class="fcal_form_item"><label class="fcal_input_content"><span class="fcal_input_label">Paiement</span><div class="fcal_payment_items"></div></label></div></div>';
const dom=new JSDOM('<html lang="fr">'+markup+'</html>',{runScripts:'outside-only'});
const w=dom.window,d=w.document;
w.fbaGuestForms={2:{allowNonparticipating:true,tariffs:[{id:'adult',title:'Option 1',cents:7000},{id:'child',title:'Option 2',cents:5500}],limit:5,nameMode:'required',emailMode:'hidden',fields:[],price:false,unit:125,currency:'EUR'}};
w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
const rows=()=>[...d.querySelectorAll('.fba-attached-guest')];
(async()=>{
 try {
  let toggle=d.querySelector('.fba-holder-participates');assert(toggle.checked);assert.match(d.querySelector('.fba-guest-summary').textContent,/70/);
  toggle.click();assert.equal(rows().length,1);assert(rows()[0].querySelector('.fba-remove-guest').disabled);assert(d.querySelector('.fba-tariff-choice').hidden);assert(d.querySelector('.fba-tariff-choice select').disabled);
  const tariff=rows()[0].querySelector('[data-fba-tariff]');tariff.value='child';tariff.dispatchEvent(new w.Event('input',{bubbles:true}));
  assert.match(d.querySelector('.fba-guest-summary').textContent,/1 personne.*55/);
  assert(!d.querySelector('.fba-payment-recap dl').textContent.includes('Vous'));
  for(let i=0;i<4;i++)d.querySelector('.fba-add-guest').click();
  assert.equal(rows().length,5);assert(d.querySelector('.fba-add-guest').disabled);
  toggle.click();assert(!toggle.checkValidity());assert.match(d.querySelector('.fba-participation-help').textContent,/Retirez/);assert.equal(rows().length,5);
  rows()[4].querySelector('.fba-remove-guest').click();assert(toggle.checkValidity());assert.equal(rows().length,4);
  toggle.click();d.querySelector('.fba-add-guest').click();assert.equal(rows().length,5);
  const payload=d.querySelector('#fcalInputIDfba_extra_2').value;
  assert.equal(JSON.parse(payload).holder_participates,false);
  const shell=d.createElement('div');shell.innerHTML=markup;shell.querySelector('input').value=payload;
  d.querySelector('.fcal_booking_form_wrap').replaceWith(shell.firstChild);
  await new Promise(r=>setTimeout(r,30));
  assert.equal(d.querySelectorAll('.fba-holder-participates').length,1);assert(!d.querySelector('.fba-holder-participates').checked);assert.equal(rows().length,5);assert.equal(rows()[0].querySelector('[data-fba-tariff]').value,'child');
  assert.equal(JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value).guests.length,5);
  Object.assign(w.fbaGuestForms[2],{allowNonparticipating:false,structuredPayload:true,tariffs:null,preservePayments:true,limit:1});
  shell.innerHTML=markup;d.querySelector('.fcal_booking_form_wrap').replaceWith(shell.firstChild);await new Promise(r=>setTimeout(r,30));
  assert(!d.querySelector('.fba-holder-participates'));
  const solo=JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value);assert.equal(solo.holder_participates,true);assert.deepEqual(solo.guests,[]);
  console.log('PASS nonparticipant DOM: 55 for one guest, no holder charge, capacity/recheck guards, five guests and opt-out retained after remount');
 } finally {w.dispatchEvent(new w.Event('pagehide'));w.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
