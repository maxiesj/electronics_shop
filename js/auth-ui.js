(function(){
  document.querySelectorAll('[data-password-toggle]').forEach(function(button){
    button.addEventListener('click',function(){
      var input=document.getElementById(button.getAttribute('data-password-toggle'));
      if(!input)return;
      var visible=input.type==='text'; input.type=visible?'password':'text';
      button.textContent=visible?'Show':'Hide'; button.setAttribute('aria-pressed',String(!visible));
    });
  });
  var password=document.querySelector('[data-password-strength]');
  if(password){
    var bar=document.querySelector('.password-meter span'); var hint=document.querySelector('.password-hint');
    var update=function(){var v=password.value,score=0;if(v.length>=8)score++;if(/[A-Z]/.test(v)&&/[a-z]/.test(v))score++;if(/\d/.test(v))score++;if(/[^A-Za-z0-9]/.test(v))score++;var widths=['0%','25%','50%','75%','100%'],colors=['#ef4444','#ef4444','#f59e0b','#3b82f6','#10b981'],labels=['Use at least 8 characters','Weak password','Fair password','Good password','Strong password'];if(bar){bar.style.width=widths[score];bar.style.background=colors[score]}if(hint)hint.textContent=labels[score]};password.addEventListener('input',update);update();
  }
  document.querySelectorAll('form').forEach(function(form){
    var password=form.querySelector('[name="password"]'); var confirm=form.querySelector('[name="confirm_password"]');
    if(!password||!confirm)return;
    var validate=function(){confirm.setCustomValidity(confirm.value&&confirm.value!==password.value?'Passwords do not match.':'')};
    password.addEventListener('input',validate); confirm.addEventListener('input',validate);
  });  document.querySelectorAll('form[data-auth-submit]').forEach(function(form){form.addEventListener('submit',function(){if(!form.checkValidity())return;var button=form.querySelector('.auth-submit');if(button){if(button.name){var trigger=document.createElement('input');trigger.type='hidden';trigger.name=button.name;trigger.value=button.value||'1';form.appendChild(trigger)}button.classList.add('is-submitting');button.disabled=true;var text=button.querySelector('[data-submit-text]');if(text)text.textContent=button.getAttribute('data-loading-text')||'Please wait...'}})});
  document.querySelectorAll('[data-email-required]').forEach(function(link){
    link.addEventListener('click',function(event){
      var email=document.getElementById('email'); if(!email)return;
      var value=email.value.trim(); email.setCustomValidity('');
      if(!value){event.preventDefault();email.setCustomValidity('Enter your registered email address before requesting a password reset.');email.reportValidity();email.focus();return;}
      if(!email.checkValidity()){event.preventDefault();email.reportValidity();email.focus();return;}
      link.href='forgot_password.php?email='+encodeURIComponent(value);
    });
  });
  var loginEmail=document.getElementById('email');
  if(loginEmail){loginEmail.addEventListener('input',function(){loginEmail.setCustomValidity('')});}  document.querySelectorAll('[data-auth-route]').forEach(function(link){
    link.addEventListener('click',function(event){
      if(event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
      var shell=document.querySelector('.auth-shell'); if(!shell)return;
      event.preventDefault();
      var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if(reduced){window.location.href=link.href;return;}
      var destination=link.getAttribute('data-auth-route');
      shell.classList.add((destination==='guest'||destination==='recovery')?'auth-route-cover':(destination==='register'?'auth-route-forward':'auth-route-back'));
      var kicker=shell.querySelector('.auth-kicker'); var title=shell.querySelector('.auth-brand-copy h1');
      window.setTimeout(function(){
        if(kicker)kicker.textContent=destination==='guest'?'Guest browsing':(destination==='recovery'?'Account recovery':(destination==='register'?'Create your account':'Welcome back'));
        if(title)title.textContent=destination==='guest'?'Opening the public ADONAK shop.':(destination==='recovery'?'Opening secure password recovery.':(destination==='register'?'Unlock secure shopping, payments and order tracking.':'Your ADONAK account is ready when you are.'));
      },270);
      window.setTimeout(function(){window.location.href=link.href;},760);
    });
  });})();