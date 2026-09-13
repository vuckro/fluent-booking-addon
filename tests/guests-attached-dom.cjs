const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
const dom=new JSDOM('<html lang="fr"><div class="fcal_booking_form_wrap"><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div></div></html>',{runScripts:'outside-only',url:'https://example.test'});
const w=dom.window,d=w.document;
w.fbaGuestForms={2:{attached:true,limit:3,nameMode:'hidden',emailMode:'optional',unit:50,currency:'EUR',price:true,seats:true,fields:[{id:'category',label:'Tarif',type:'radio',required:true,choices:['Adulte','Enfant'],pricing:'replace',prices:[5000,2500]},{id:'extra',label:'Supplément',type:'checkbox',required:false,choices:[],pricing:'add',prices:[1000]}]}};
w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
const rows=()=>[...d.querySelectorAll('.fcal_multi_guest_input')];
try {
 const add=d.querySelector('.fba-add-guest');add.click();add.click();
 assert.equal(rows().length,2);assert(add.disabled);assert(rows()[0].querySelector('label').hidden);
 const a=rows()[0],b=rows()[1];
 a.querySelector('input[value=Adulte]').click();a.querySelector('input[type=checkbox]').click();b.querySelector('input[value=Enfant]').click();
 assert.match(d.querySelector('.fba-guest-summary').textContent,/135/);
 const values=JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value);
 assert.equal(values[0].email,'');assert.equal(values[0].fields.extra,'1');
 a.querySelector('button').click();assert.equal(rows().length,1);assert.match(d.querySelector('.fba-guest-summary').textContent,/75/);
 assert.equal(rows()[0].querySelector('input[value=Enfant]').checked,true);
 console.log('PASS attached DOM: hidden identity, radios, supplement, 135 total, removal keeps child and 75 total');
} finally {w.dispatchEvent(new w.Event('pagehide'));w.close();}
