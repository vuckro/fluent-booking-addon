// The custom form owns its rows; no dependency on Svelte's row reuse.
const {JSDOM}=require('jsdom'), fs=require('fs'), assert=require('assert');
const dom=new JSDOM('<html lang="fr"><div class="fcal_booking_form_wrap"><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div></div></html>',{runScripts:'outside-only',url:'https://example.test'});
const w=dom.window,d=w.document;
w.fbaGuestForms={2:{limit:3,nameMode:'required',emailMode:'required',price:true,unit:100,currency:'EUR',fields:[{id:'category',label:'Catégorie',type:'select',required:true,choices:['Adulte','Enfant']}]}};
w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
try {
 const add=d.querySelector('.fba-add-guest');
 assert.match(d.querySelector('.fba-guest-summary').textContent,/100/);
 add.click();const a=d.querySelector('.fba-attached-guest');
 assert.match(d.querySelector('.fba-guest-summary').textContent,/200/);
 assert(a.querySelector('[data-fba-identity=name]').required);
 a.querySelector('[data-fba-identity=email]').value='a@example.test';a.querySelector('select').value='Adulte';
 add.click();const b=d.querySelectorAll('.fba-attached-guest')[1];
 b.querySelector('[data-fba-identity=email]').value='b@example.test';b.querySelector('select').value='Enfant';b.querySelector('select').dispatchEvent(new w.Event('change',{bubbles:true}));
 assert.match(d.querySelector('.fba-guest-summary').textContent,/300/);assert(add.disabled);
 a.querySelector('button').click();
 const payload=JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value);
 assert.equal(payload.length,1);assert.equal(payload[0].email,'b@example.test');assert.equal(payload[0].fields.category,'Enfant');assert(!add.disabled);
 assert.match(d.querySelector('.fba-guest-summary').textContent,/200/);
 console.log('PASS single guest form: 100/200/300, required identity, capacity limit and stable answers after removal');
} finally {w.dispatchEvent(new w.Event('pagehide'));w.close();}
