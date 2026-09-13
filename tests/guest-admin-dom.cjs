const {JSDOM}=require('jsdom'),fs=require('fs'),assert=require('assert');
const dom=new JSDOM(`<div class="fba-guest-options"><input name="guest_options[enabled]" type="checkbox"><div class="fba-guest-details"><div class="fba-guest-fields"><fieldset class="fba-extra-field"><select><option value="select">Liste</option><option value="text">Texte</option></select><label class="fba-field-choices"></label><div class="fba-field-pricing"><select><option value="none">Aucun</option><option value="add">Supplément</option></select><label class="fba-field-prices"></label></div></fieldset></div><button class="fba-add-field"></button><template></template></div></div>`,{runScripts:'outside-only'});
const w=dom.window,d=w.document;
w.eval(fs.readFileSync('assets/admin/settings.js','utf8'));
const toggle=d.querySelector('input'),panel=d.querySelector('.fba-guest-details');
assert(panel.hidden);toggle.click();assert(!panel.hidden);
const pricing=d.querySelector('.fba-field-pricing select');pricing.value='add';pricing.dispatchEvent(new w.Event('change',{bubbles:true}));assert(!d.querySelector('.fba-field-prices').hidden);
toggle.click();assert(panel.hidden);assert.equal(pricing.value,'add');
console.log('PASS admin: options hidden until enabled and selections preserved while collapsed');w.close();
