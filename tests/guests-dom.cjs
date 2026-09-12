// NODE_PATH points to a temporary jsdom install; not a plugin dependency.
const {JSDOM}=require('jsdom');
const fs=require('fs');
const assert=require('assert');
const dom=new JSDOM(`<html lang="fr"><div class="fcal_booking_form_wrap"><div class="fcal_form_item"><input id="fcalInputIDfba_extra_2"></div><div class="fcal_input_multi_guests_wrap"></div><table class="fcal_payment_items_table"></table></div></html>`,{runScripts:'outside-only',url:'https://example.test'});
const w=dom.window,d=w.document;
w.fbaGuestForms={2:{fields:[{id:'category',label:'Catégorie',type:'select',required:true,choices:['Adulte','Enfant']}],seats:true,price:true,unit:100,currency:'EUR'}};
w.eval(fs.readFileSync('assets/public/guests.js','utf8'));
const tick=()=>new Promise(resolve=>setTimeout(resolve,20));
const row=email=>{const r=d.createElement('div');r.className='fcal_multi_guest_input';r.innerHTML='<div><input type="text"><input type="email"><button type="button">Supprimer</button></div>';r.querySelector('input[type=email]').value=email;return r;};
(async()=>{
 await tick();assert.match(d.querySelector('.fba-guest-summary').textContent,/100/);
 const wrap=d.querySelector('.fcal_input_multi_guests_wrap');
 const a=row('a@example.test');wrap.append(a);await tick();
 assert.match(d.querySelector('.fba-guest-summary').textContent,/200/);
 assert.equal(a.querySelector('select').required,true);
 a.querySelector('select').value='Adulte';a.querySelector('select').dispatchEvent(new w.Event('change',{bubbles:true}));
 const b=row('b@example.test');wrap.append(b);await tick();
 b.querySelector('select').value='Enfant';b.querySelector('select').dispatchEvent(new w.Event('change',{bubbles:true}));
 assert.match(d.querySelector('.fba-guest-summary').textContent,/300/);
 let payload=JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value);assert.equal(payload[1].fields.category,'Enfant');
 // Simulate Svelte's unkeyed row reuse after deleting the first guest.
 a.querySelector('button').addEventListener('click',()=>{a.querySelector('input[type=email]').value='b@example.test';b.remove();});
 a.querySelector('button').click();await tick();
 payload=JSON.parse(d.querySelector('#fcalInputIDfba_extra_2').value);
 assert.equal(payload.length,1);assert.equal(payload[0].email,'b@example.test');assert.equal(payload[0].fields.category,'Enfant');
 console.log('PASS DOM: 100/200/300 preview, required category, serialization and guest removal identity');
 w.dispatchEvent(new w.Event('pagehide'));w.close();
})().catch(e=>{w.dispatchEvent(new w.Event('pagehide'));w.close();console.error(e);process.exit(1);});
