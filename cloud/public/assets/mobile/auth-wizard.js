/* SafirCharge — ویزارد ورود / ثبت‌نام */
(function () {
  'use strict';

  var state = {
    phone: '',
    registered: false,
    hasPassword: false,
    registrationToken: null,
    registrationProof: null,
    backendKind: 'cloud',
  };

  var api = window.SC_AUTH_API || {};
  var csrf = document.querySelector('meta[name="csrf-token"]');

  function normPhone(v) {
    var d = (v || '').replace(/[^\d]/g, '');
    if (d.indexOf('98') === 0 && d.length === 12) d = '0' + d.slice(2);
    return d;
  }

  function faDigits(s) {
    return String(s).replace(/\d/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'[d];
    });
  }

  function show(id) {
    ['stepPhone', 'stepPassword', 'stepOtp', 'stepRegister'].forEach(function (sid) {
      var el = document.getElementById(sid);
      if (el) el.hidden = sid !== id;
    });
  }

  function setMsg(id, text, isError) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = text || '';
    el.style.color = isError ? 'var(--red)' : 'var(--text-2)';
  }

  function activeBackend() {
    return window.SC_ACTIVE_BACKEND || { kind: 'cloud', base: '' };
  }

  function cloudFetch(path, body) {
    var headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrf) headers['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    return fetch(path, {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, d: d }; });
    });
  }

  function backendFetch(cloudPath, edgePath, body) {
    var backend = activeBackend();
    if (backend.kind === 'edge' && backend.base) {
      return fetch(backend.base + edgePath, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {}),
      }).then(function (r) {
        return r.json().then(function (d) { return { ok: r.ok, d: d }; });
      });
    }
    return cloudFetch(cloudPath, body);
  }

  function updateBackendBadge() {
    var badge = document.getElementById('authBackendBadge');
    if (!badge) return;
    var b = activeBackend();
    badge.textContent = b.kind === 'edge' ? 'لبه ایستگاه' : 'مرکز ابری';
    badge.className = 'pill ' + (b.kind === 'edge' ? 'pill-green' : 'pill-blue');
    state.backendKind = b.kind;
  }

  function lookupPhone() {
    var phone = normPhone(document.getElementById('authPhone').value);
    if (phone.length < 10) {
      setMsg('phoneMsg', 'شماره موبایل معتبر وارد کنید.', true);
      return;
    }
    state.phone = phone;
    setMsg('phoneMsg', 'در حال بررسی…');
    var btn = document.getElementById('btnPhoneNext');
    btn.disabled = true;

    (window.scEnsureBackend ? window.scEnsureBackend() : Promise.resolve())
      .then(updateBackendBadge)
      .then(function () {
        return backendFetch(api.lookupCloud, api.lookupEdge, { phone: phone });
      })
      .then(function (res) {
        if (!res.ok) throw new Error(res.d.message || 'خطا در بررسی شماره');
        state.registered = !!res.d.registered;
        state.hasPassword = !!res.d.has_password;

        if (!state.registered && activeBackend().kind === 'edge') {
          return cloudFetch(api.lookupCloud, { phone: phone }).then(function (cloudRes) {
            if (cloudRes.ok && cloudRes.d.registered) {
              state.registered = true;
              state.hasPassword = !!cloudRes.d.has_password;
            }
            return res;
          });
        }
        return res;
      })
      .then(function () {
        document.getElementById('otpPhoneLabel').textContent = faDigits(state.phone);
        document.getElementById('regPhoneLabel').textContent = faDigits(state.phone);

        if (state.registered && state.hasPassword) {
          document.getElementById('pwdPhoneLabel').textContent = faDigits(phone);
          show('stepPassword');
          setMsg('phoneMsg', '');
        } else {
          requestOtp();
        }
      })
      .catch(function (err) {
        setMsg('phoneMsg', err.message || 'خطا', true);
      })
      .finally(function () { btn.disabled = false; });
  }

  function requestOtp() {
    setMsg('otpMsg', 'در حال ارسال کد…');
    backendFetch(api.otpRequestCloud, api.otpRequestEdge, {
      phone: state.phone,
      purpose: 'register',
    }).then(function (res) {
      if (!res.ok) throw new Error(res.d.message || 'خطا در ارسال کد');
      show('stepOtp');
      setMsg('otpMsg', res.d.message || 'کد ارسال شد');
      if (res.d.debug_code) {
        var hint = document.getElementById('otpTestHint');
        hint.hidden = false;
        var codes = res.d.test_codes_hint || [res.d.debug_code];
        hint.textContent = 'کدهای تست: ' + codes.join(' ، ');
        fillOtp(res.d.debug_code);
      }
      startTimer();
    }).catch(function (err) {
      setMsg('otpMsg', err.message, true);
    });
  }

  function fillOtp(code) {
    var cells = document.querySelectorAll('#otpCells .cell');
    var chars = String(code).split('');
    cells.forEach(function (cell, i) {
      cell.textContent = chars[i] || '_';
      cell.classList.toggle('filled', !!chars[i]);
    });
    document.getElementById('authOtpValue').value = code;
  }

  function readOtpFromCells() {
    var code = '';
    document.querySelectorAll('#otpCells .cell').forEach(function (c) {
      var t = (c.textContent || '').trim();
      if (t !== '_' && t !== '') code += t.replace(/[^\d]/g, '');
    });
    return code;
  }

  function verifyOtp() {
    var code = document.getElementById('authOtpValue').value || readOtpFromCells();
    if (code.length !== 6) {
      setMsg('otpMsg', 'کد ۶ رقمی را کامل وارد کنید.', true);
      return;
    }
    setMsg('otpMsg', 'در حال تأیید…');
    backendFetch(api.otpVerifyCloud, api.otpVerifyEdge, {
      phone: state.phone,
      code: code,
      purpose: 'register',
    }).then(function (res) {
      if (!res.ok) throw new Error(res.d.message || 'کد نامعتبر');
      state.registrationToken = res.d.registration_token;
      state.registrationProof = res.d.registration_proof || null;
      if (res.d.edge_session_token) {
        try {
          localStorage.setItem('sc_edge_session_token', res.d.edge_session_token);
        } catch (e) {}
      }
      show('stepRegister');
      setMsg('otpMsg', '');
    }).catch(function (err) {
      setMsg('otpMsg', err.message, true);
    });
  }

  function loginPassword() {
    var password = document.getElementById('authPassword').value;
    setMsg('pwdMsg', 'در حال ورود…');
    cloudFetch(api.loginCloud, { phone: state.phone, password: password })
      .then(function (res) {
        if (!res.ok) throw new Error(res.d.message || 'ورود ناموفق');
        window.location.href = api.homeUrl || '/app';
      })
      .catch(function (err) {
        setMsg('pwdMsg', err.message, true);
      });
  }

  function registerAccount() {
    var pwd = document.getElementById('regPassword').value;
    var pwd2 = document.getElementById('regPasswordConfirm').value;
    if (pwd.length < 8) {
      setMsg('regMsg', 'رمز عبور حداقل ۸ کاراکتر باشد.', true);
      return;
    }
    if (pwd !== pwd2) {
      setMsg('regMsg', 'تکرار رمز با رمز عبور یکسان نیست.', true);
      return;
    }
    setMsg('regMsg', 'در حال ثبت‌نام…');
    cloudFetch(api.registerCloud, {
      phone: state.phone,
      password: pwd,
      password_confirmation: pwd2,
      registration_token: state.registrationToken,
      registration_proof: state.registrationProof,
    }).then(function (res) {
      if (!res.ok) throw new Error(res.d.message || 'ثبت‌نام ناموفق');
      window.location.href = api.homeUrl || '/app';
    }).catch(function (err) {
      setMsg('regMsg', err.message, true);
    });
  }

  var timerIv = null;
  function startTimer() {
    var t = 60;
    var el = document.getElementById('otpTimer');
    if (timerIv) clearInterval(timerIv);
    timerIv = setInterval(function () {
      t--;
      if (t < 0) {
        clearInterval(timerIv);
        if (el) el.textContent = '۰۰:۰۰';
        return;
      }
      var s = t < 10 ? '۰' + faDigits(t) : faDigits(t);
      if (el) el.textContent = '۰۰:' + s;
    }, 1000);
  }

  function bindOtpCells() {
    var cells = document.querySelectorAll('#otpCells .cell');
    cells.forEach(function (cell, idx) {
      cell.addEventListener('click', function () {
        document.getElementById('authOtpValue').focus();
      });
    });
    var input = document.getElementById('authOtpValue');
    if (!input) return;
    input.addEventListener('input', function () {
      var v = input.value.replace(/\D/g, '').slice(0, 6);
      input.value = v;
      var chars = v.split('');
      cells.forEach(function (cell, i) {
        cell.textContent = chars[i] || '_';
        cell.classList.toggle('filled', !!chars[i]);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindOtpCells();
    document.getElementById('btnPhoneNext').addEventListener('click', lookupPhone);
    document.getElementById('btnPwdLogin').addEventListener('click', loginPassword);
    document.getElementById('btnOtpVerify').addEventListener('click', verifyOtp);
    document.getElementById('btnRegister').addEventListener('click', registerAccount);
    document.getElementById('btnOtpResend').addEventListener('click', requestOtp);
    document.getElementById('backFromPwd').addEventListener('click', function () { show('stepPhone'); });
    document.getElementById('backFromOtp').addEventListener('click', function () { show('stepPhone'); });
    document.getElementById('backFromReg').addEventListener('click', function () { show('stepOtp'); });

    (window.scEnsureBackend ? window.scEnsureBackend() : Promise.resolve()).then(updateBackendBadge);
  });
})();
