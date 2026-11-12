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

const adminDom=new JSDOM('<html lang="fr"><div class="el-dialog fcal_dialog fcal_Modal fcal_new_booking"><div class="el-dialog__body"><form class="el-form">'
 + '<div class="el-form-item"><label class="el-form-item__label">Sélectionner un événement *</label><div class="el-form-item__content"><div class="el-select"><input type="text" class="el-select__input"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Fuseau horaire du participant *</label><div class="el-form-item__content"><div class="el-select"><input type="text" class="el-select__input"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Durée de la réunion *</label><div class="el-form-item__content"><div class="el-select"><input type="text" class="el-select__input"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Sélectionner la date *</label><div class="el-form-item__content"><div class="fcal_event_date"><input type="text"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Sélectionner le statut</label><div class="el-form-item__content"><div class="el-select"><input type="text" class="el-select__input"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Nom du participant *</label><div class="el-form-item__content"><input class="el-input__inner" type="text"></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Email du participant *</label><div class="el-form-item__content"><div class="el-select"><input type="text" class="el-select__input" placeholder="Recherchez un contact CRM ou saisissez une adresse email"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Téléphone *</label><div class="el-form-item__content"><input type="tel"></div></div>'
 + '<div id="transport-wrapper"><div class="fcal_new_booking_guest"><div class="el-form-item"><input type="text" placeholder="fba_extra_2"></div></div></div>'
 + '<div class="el-form-item"><label class="el-form-item__label">Lieu *</label><div class="el-form-item__content"><div class="el-radio-group"><input type="radio" name="location"></div></div></div>'
 + '</form></div><div class="el-dialog__footer"><div class="dialog-footer"><button type="button" class="el-button fcal_primary_btn">Create Booking</button></div></div></div></html>',{runScripts:'outside-only',url:'https://example.test'});
const aw=adminDom.window,ad=aw.document;
aw.fbaGuestForms={2:{allowNonparticipating:true,splitName:true,tariffs:[{id:'adult',title:'Adulte',cents:7000},{id:'child',title:'Enfant',cents:5500}],limit:5,nameMode:'required',emailMode:'hidden',price:true,unit:125,currency:'EUR',fields:[{id:'age',label:'Âge du participant',type:'number',required:true,choices:[],min:8,max:99}]}};
aw.eval(fs.readFileSync('assets/public/guests.js','utf8'));
(async () => {
try {
 const transport=ad.querySelector('input[placeholder="fba_extra_2"]');
 const transportWrapper=ad.getElementById('transport-wrapper');
 const form=ad.querySelector('form.el-form');
 assert(transport.closest('.el-form-item').hidden && transport.closest('.el-form-item').style.display==='none','admin transport item hidden');
 assert(transportWrapper.hidden && transportWrapper.style.display==='none','admin transport outer wrapper hidden');
 const labels=[...ad.querySelectorAll('.el-form-item__label')].map(el=>el.textContent.trim());
 assert.equal(labels[0],'Sélectionner un événement *','event select label preserved');
 assert.equal(labels[1],'Fuseau horaire du participant *','timezone select label preserved');
 assert.equal(labels[2],'Durée de la réunion *','duration select label preserved');
 assert.equal(labels[4],'Sélectionner le statut','status select label preserved');
 assert.equal(labels[5],'Nom complet du réservant *','contact name relabeled');
 assert.equal(labels[6],'Email du réservant *','contact email relabeled');
 assert.equal(labels[7],'Téléphone *','phone label preserved');
 assert.equal(labels[8],'Lieu *','location label preserved');
 const nameInput=ad.querySelectorAll('.el-form-item')[5].querySelector('input[type="text"]');
 const crmEmailInput=ad.querySelectorAll('.el-form-item')[6].querySelector('.el-select__input');
 assert.equal(nameInput.getAttribute('placeholder'),'Prénom et nom (ex. Francine Sauser)','contact name placeholder clarified');
 assert.equal(crmEmailInput.getAttribute('placeholder'),'Recherchez un contact CRM ou saisissez une adresse email','CRM search placeholder preserved');
 const formChildren=[...form.children];
 const phoneIdx=formChildren.findIndex(el=>el.querySelector('input[type="tel"]'));
 const partHeadingIdx=formChildren.findIndex(el=>el.classList.contains('fba-section-heading') && el.textContent.trim()==='Participants à l’événement');
 const guestWrapIdx=formChildren.findIndex(el=>el.classList.contains('fcal_input_multi_guests_wrap'));
 const recapIdx=formChildren.findIndex(el=>el.classList.contains('fba-payment-recap'));
 const locationIdx=formChildren.findIndex(el=>el.querySelector('.el-radio-group'));
 assert(partHeadingIdx>phoneIdx && guestWrapIdx>partHeadingIdx && recapIdx>guestWrapIdx && locationIdx>recapIdx,'injected sections sit directly under <form> between Téléphone * and Lieu *');
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
 transport.closest('.fcal_new_booking_guest').remove();
 await new Promise(r=>setTimeout(r,20));
 assert.equal(ad.querySelectorAll('.fba-section-heading').length,0,'admin headings removed when switching to non-custom event');
 assert(!transportWrapper.hidden && transportWrapper.style.display==='','outer wrapper visibility restored on unmount');
 assert.equal(ad.querySelectorAll('.el-form-item__label')[5].textContent.trim(),'Nom du participant *','native name label restored');
 assert.equal(ad.querySelectorAll('.el-form-item__label')[6].textContent.trim(),'Email du participant *','native email label restored');
 console.log('PASS admin modal guest form: exact Element Plus selectors untouched, contact vs participant clarity, top-level placement, nonparticipating holder, split name, tariff, age validation & clean unmount');
} finally {aw.dispatchEvent(new aw.Event('pagehide'));aw.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
