'use strict';
(()=>{
const form=document.getElementById('enquiry-form'),type=document.getElementById('enquiry-type'),submit=document.getElementById('submit-button'),alert=document.getElementById('form-error'),success=document.getElementById('success'),availability=document.getElementById('availability');
const endpoint=window.PHYSIO_CONFIG?.formEndpoint||'';
const apiKey=window.PHYSIO_CONFIG?.emailApiKey||'';
const configured=/^https:\/\//.test(endpoint)&&Boolean(apiKey);
let sending=false;
submit.disabled=!configured;
if(!configured)availability.textContent='Online enquiries are not available yet. Please call Tejas to arrange a demonstration.';
class EnquiryApiError extends Error {
  constructor(message,status,errors={}){super(message);this.status=status;this.errors=errors;}
}
async function sendEnquiryToEmailApi(enquiry,signal){
  if(!configured)throw new EnquiryApiError('Online enquiries are not available yet. Please call +91 98256 47083.',0);
  const response=await fetch(endpoint,{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-Api-Key':apiKey},
    body:JSON.stringify({
      full_name:enquiry.fullName,clinic_name:enquiry.clinicName,city:enquiry.city,
      mobile_number:enquiry.mobile,email:enquiry.email,enquiry_type:enquiry.enquiryType,
      message:enquiry.message,website:enquiry.website,
    }),signal,
  });
  let result;
  try{result=await response.json();}catch{throw new EnquiryApiError('We could not confirm your request was received. Please try again or call +91 98256 47083.',response.status);}
  if(response.status!==200||result?.ok!==true){
    let message=typeof result?.message==='string'?result.message:'Your request could not be sent. Please try again.';
    if([400,403,404,405].includes(response.status))message='Online enquiries are temporarily unavailable. Please call +91 98256 47083.';
    if(response.status===500)message+=' Please try again later.';
    throw new EnquiryApiError(message,response.status,response.status===422&&result?.errors&&typeof result.errors==='object'?result.errors:{});
  }
  return result;
}
function selectType(value){type.value=value;document.querySelectorAll('[data-type]').forEach(b=>{const active=b.dataset.type===value;b.classList.toggle('selected',active);b.setAttribute('aria-pressed',String(active));});submit.textContent=value==='Product demo'?'Send demo request':'Request free workflow audit';}
document.querySelectorAll('[data-type]').forEach(b=>b.addEventListener('click',()=>selectType(b.dataset.type)));
document.querySelectorAll('[data-enquiry]').forEach(a=>a.addEventListener('click',()=>selectType(a.dataset.enquiry)));
type.addEventListener('change',()=>selectType(type.value));
const fieldIds={full_name:'full-name',clinic_name:'clinic-name',city:'city',mobile_number:'mobile',email:'email',enquiry_type:'enquiry-type',message:'message'};
function clearErrors(){alert.hidden=true;for(const id of Object.values(fieldIds)){const input=document.getElementById(id);input.setAttribute('aria-invalid','false');document.getElementById(id+'-error').textContent='';}}
function setFieldError(id,message){const input=document.getElementById(id);document.getElementById(id+'-error').textContent=message;input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby',id==='message'?'message-help message-error':id+'-error');}
function validate(){
  let first=null;
  for(const id of Object.values(fieldIds)){
    const input=document.getElementById(id),value=input.value.trim();let error='';
    if(input.required&&!value)error='Please complete this field.';
    else if(value&&input.maxLength>0&&value.length>input.maxLength)error='Please keep this field within '+input.maxLength+' characters.';
    else if(id==='mobile'&&value&&(!/^[+\d ().-]+$/.test(value)||value.replace(/\D/g,'').length<7||value.replace(/\D/g,'').length>15))error='Enter a mobile number with 7–15 digits. You may use +, spaces, hyphens, brackets, or dots.';
    else if(id==='email'&&value&&!input.validity.valid)error='Enter a valid email address.';
    if(error){setFieldError(id,error);if(!first)first=input;}
  }
  if(first)first.focus();return !first;
}
form.addEventListener('submit',async e=>{
  e.preventDefault();if(sending)return;clearErrors();if(!validate())return;
  if(!configured){alert.textContent='Online enquiries are not available yet. Please call +91 98256 47083.';alert.hidden=false;return;}
  if(form.elements.website.value){alert.textContent='Your request could not be sent. Please call Tejas.';alert.hidden=false;return;}
  sending=true;submit.disabled=true;submit.textContent='Sending…';
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
  try{
    const data=Object.fromEntries(new FormData(form));for(const key of Object.keys(data))data[key]=String(data[key]).trim();
    const result=await sendEnquiryToEmailApi(data,controller.signal);
    document.getElementById('success-message').textContent=typeof result.message==='string'&&result.message?result.message:'Your enquiry has been sent. Tejas will contact you using the details provided.';
    form.reset();selectType(type.value);form.hidden=true;success.hidden=false;success.focus();
  }catch(error){
    let first=null;
    if(error instanceof EnquiryApiError){for(const [key,message]of Object.entries(error.errors)){const id=fieldIds[key];if(typeof id==='string'&&typeof message==='string'){setFieldError(id,message);if(!first)first=document.getElementById(id);}}}
    alert.textContent=error instanceof EnquiryApiError?error.message:'We could not confirm your request was received. Please try again or call +91 98256 47083.';
    alert.hidden=false;if(first)first.focus();
  }finally{clearTimeout(timer);sending=false;submit.disabled=!configured;selectType(type.value);}
});
document.getElementById('new-enquiry').addEventListener('click',()=>{clearErrors();selectType('Product demo');success.hidden=true;form.hidden=false;document.getElementById('full-name').focus();});
// Keep the FAQ exclusive, including browsers without native details grouping.
const faqItems=Array.from(document.querySelectorAll('.faq-list details'));
faqItems.forEach(item=>item.addEventListener('toggle',()=>{
  if(item.open)faqItems.forEach(other=>{if(other!==item)other.open=false;});
}));
// Workflow media: click/tap, mouse hover, and keyboard focus select the step.
const journeyButtons=Array.from(document.querySelectorAll('[data-journey]'));
const journeyPanels=Array.from(document.querySelectorAll('[data-journey-panel]'));
function activateJourney(index){
  journeyButtons.forEach((button,i)=>{const active=i===index;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));});
  journeyPanels.forEach((panel,i)=>{panel.hidden=i!==index;});
}
journeyButtons.forEach((button,index)=>{
  button.addEventListener('click',()=>activateJourney(index));
  button.addEventListener('pointerenter',event=>{if(event.pointerType==='mouse')activateJourney(index);});
  button.addEventListener('focus',()=>activateJourney(index));
  button.addEventListener('keydown',event=>{
    let next=index;
    if(event.key==='ArrowDown')next=(index+1)%journeyButtons.length;
    else if(event.key==='ArrowUp')next=(index-1+journeyButtons.length)%journeyButtons.length;
    else if(event.key==='Home')next=0;
    else if(event.key==='End')next=journeyButtons.length-1;
    else return;
    event.preventDefault();activateJourney(next);journeyButtons[next].focus();
  });
});
})();
