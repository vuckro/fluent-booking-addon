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

const adminDom=new JSDOM('<html lang="fr"><div class="el-dialog fcal_dialog fcal_Modal fcal_new_booking"><div class="el-dialog__body"><form class="el-form"><div class="el-form-item"><label class="el-form-item__label">Nom du participant *</label><div class="el-form-item__content"><input type="text"></div></div><div class="el-form-item"><label class="el-form-item__label">Email du participant *</label><div class="el-form-item__content"><input type="text"></div></div><div><div><div class="el-form-item"><input placeholder="fba_extra_2"></div></div></div></form></div><div class="el-dialog__footer"><div class="dialog-footer"><button type="button" class="el-button fcal_primary_btn">Create Booking</button></div></div></div></html>',{runScripts:'outside-only',url:'https://example.test'});
const aw=adminDom.window,ad=aw.document;
aw.fbaGuestForms={2:{allowNonparticipating:true,splitName:true,tariffs:[{id:'adult',title:'Adulte',cents:7000},{id:'child',title:'Enfant',cents:5500}],limit:5,nameMode:'required',emailMode:'hidden',price:true,unit:125,currency:'EUR',fields:[{id:'age',label:'Âge du participant',type:'number',required:true,choices:[],min:8,max:99}]}};
aw.eval(fs.readFileSync('assets/public/guests.js','utf8'));
(async () => {
try {
 const transport=ad.querySelector('input[placeholder="fba_extra_2"]');
 assert(transport.closest('.el-form-item').hidden,'admin transport field hidden');
 const labels=[...ad.querySelectorAll('.el-form-item__label')].map(el=>el.textContent.trim());
 assert.deepEqual(labels.slice(0,2),['Nom complet du réservant *','Email du réservant *'],'admin modal clarifies contact vs participant fields');
 const headings=[...ad.querySelectorAll('.fba-section-heading')].map(el=>el.textContent.trim());
 assert.deepEqual(headings,['Coordonnées du réservant (contact)','Participants à l’événement'],'admin modal renders clear section headings');
 assert.equal(ad.querySelector('.fba-participation-choice').textContent.trim(),'Le réservant participe également');
 const toggle=ad.querySelector('.fba-holder-participates');
 toggle.click();
 assert.match(ad.querySelector('.fba-participation-help').textContent,/Le réservant ne participe pas/);
 const guest=ad.querySelector('.fba-attached-guest');
 guest.querySelector('[data-fba-identity=first_name]').value='Léo';
 guest.querySelector('[data-fba-identity=last_name]').value='Martin';
 guest.querySelector('[data-fba-tariff]').value='child';
 const ageInput=guest.querySelector('[data-fba-answer=age]');
 ageInput.value='6';
 let submitted=false;
 const btn=ad.querySelector('.fcal_primary_btn');
 btn.addEventListener('click',()=>{submitted=true;});
 btn.click();
 assert(!submitted,'admin submit button blocked when participant age < min');
 ageInput.value='11';
 btn.click();
 assert(submitted,'admin submit button proceeds when participant fields are valid');
 const payload=JSON.parse(transport.value);
 assert.equal(payload.holder_participates,false);
 assert.equal(payload.guests.length,1);
 assert.equal(payload.guests[0].first_name,'Léo');
 assert.equal(payload.guests[0].last_name,'Martin');
 assert.equal(payload.guests[0].tariff,'child');
 assert.equal(payload.guests[0].fields.age,'11');
 // Switching to a regular event removes custom nodes and restores native labels.
 transport.closest('div').remove();
 await new Promise(r=>setTimeout(r,20));
 assert.equal(ad.querySelectorAll('.fba-section-heading').length,0,'admin headings removed when switching to non-custom event');
 assert.deepEqual([...ad.querySelectorAll('.el-form-item__label')].map(el=>el.textContent.trim()),['Nom du participant *','Email du participant *'],'native labels restored on non-custom event');
 console.log('PASS admin modal guest form: contact vs participant clarity, nonparticipating holder, split name, tariff, age validation & clean unmount');
} finally {aw.dispatchEvent(new aw.Event('pagehide'));aw.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
