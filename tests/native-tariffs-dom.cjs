const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
const dom=new JSDOM('<html lang="fr"><div class="fluent_booking_app"><div class="fcal_slot_info"><div class="fcal_slot_payment_item">125 €</div></div><div class="fcal_booking_form_wrap"><div class="fcal_payment_items_wrapper"><div class="fcal_payment_items">Native</div><div class="fcal_payment_methods">Offline</div></div><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div></div></div></html>',{runScripts:'outside-only',url:'https://example.test'});
const w=dom.window,d=w.document;
w.fbaGuestForms={2:{limit:5,nameMode:'required',emailMode:'hidden',unit:125,currency:'EUR',price:true,fields:[{id:'age',label:'Âge',type:'number',required:true,choices:[]}],tariffs:[{id:'adult',title:'Adulte',cents:7000},{id:'child',title:'Enfant',cents:5500}]}};
w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
const update=el=>el.dispatchEvent(new w.Event('input',{bubbles:true}));
try {
 const transport=d.querySelector('#fcalInputIDfba_extra_2');assert(transport.closest('.fcal_form_item').hidden);
 const holder=d.querySelector('[data-fba-tariff]');assert.equal(holder.value,'adult');assert.match(d.querySelector('.fba-guest-summary').textContent,/70/);
 holder.value='child';update(holder);assert.match(d.querySelector('.fba-sidebar-total').textContent,/55/);
 holder.value='adult';update(holder);
 d.querySelector('.fba-add-guest').click();const guest=d.querySelector('.fba-attached-guest');
 assert(guest.querySelector('[data-fba-identity=email]').disabled);assert(guest.querySelector('[data-fba-identity=name]').required);
 guest.querySelector('[data-fba-identity=name]').value='Camille';guest.querySelector('[data-fba-tariff]').value='child';guest.querySelector('[data-fba-answer=age]').value='8';update(guest.querySelector('[data-fba-tariff]'));
 assert.match(d.querySelector('.fba-guest-summary').textContent,/125/);assert.match(d.querySelector('.fba-payment-recap').textContent,/Camille · Enfant/);
 let data=JSON.parse(transport.value);assert.equal(data.holder_tariff,'adult');assert.equal(data.guests[0].tariff,'child');assert.equal(data.guests[0].fields.age,'8');
 assert(d.querySelector('.fcal_payment_methods'));assert(!d.querySelector('.fba-payment-recap').closest('.fcal_payment_items'));
 guest.querySelector('button').click();assert.match(d.querySelector('.fba-guest-summary').textContent,/70/);assert.equal(JSON.parse(transport.value).guests.length,0);
 console.log('PASS native tariff DOM: 70/55/125, named child, hidden email, custom age, payment provider retained, guest removal');
} finally {w.dispatchEvent(new w.Event('pagehide'));w.close();}
