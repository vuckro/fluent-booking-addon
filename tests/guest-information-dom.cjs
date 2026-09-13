const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
const dom=new JSDOM('<div class="fcal_booking_form_wrap"><div class="fcal_form_item"><label class="fcal_input_content"><span class="fcal_input_label">Paiement</span><div class="fcal_payment_items">Montant natif</div></label></div><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div></div>',{runScripts:'outside-only'});
const w=dom.window,d=w.document;
w.fbaGuestForms={2:{preservePayments:true,tariffs:null,limit:5,nameMode:'required',emailMode:'hidden',fields:[{id:'number',label:'Nombre',type:'number',required:false,min:'0',max:'10'}],price:false,unit:70,currency:'EUR'}};
w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
try {
 assert(!d.querySelector('.fba-custom-pricing'));
 assert(!d.querySelector('.fba-payment-methods'));
 assert(d.querySelector('.fba-payment-recap').hidden);
 assert.equal(d.querySelector('.fcal_payment_items').textContent,'Montant natif');
 d.querySelector('.fba-add-guest').click();
 const n=d.querySelector('[data-fba-answer=number]');
 assert.equal(n.min,'0');assert.equal(n.max,'10');
 n.value='11';assert(!n.checkValidity());n.value='0';assert(n.checkValidity());
 const name=d.querySelector('[data-fba-identity=name]');name.value='Camille';n.dispatchEvent(new w.Event('input',{bubbles:true}));
 assert.equal(JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value)[0].fields.number,'0');
 console.log('PASS information-only DOM: native payment retained, identity and numeric bounds work independently');
} finally {w.dispatchEvent(new w.Event('pagehide'));w.close();}
