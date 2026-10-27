(() => {
    'use strict';
    const decode = text => Uint8Array.from(atob(text.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - text.length % 4) % 4)), c => c.charCodeAt(0));
    const encode = bytes => btoa(String.fromCharCode(...new Uint8Array(bytes))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    const post = async (url, fields) => {
        const response = await fetch(url, {method: 'POST', credentials: 'same-origin', redirect: 'error',
            headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content}, body: new URLSearchParams(fields)});
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Verification failed');
        return data;
    };
    document.querySelectorAll('[data-um-passkey]').forEach(button => button.addEventListener('click', async () => {
        const feedback = document.getElementById('um-security-feedback');
        button.disabled = true; feedback.textContent = '';
        try {
            if (!window.isSecureContext || !window.PublicKeyCredential) throw new Error(button.dataset.unsupported);
            const operation = button.dataset.umPasskey;
            const options = await post(button.dataset.options, {operation});
            options.challenge = decode(options.challenge);
            if (options.user) options.user.id = decode(options.user.id);
            ['allowCredentials', 'excludeCredentials'].forEach(field => (options[field] || []).forEach(item => { item.id = decode(item.id); }));
            const credential = operation === 'register' ? await navigator.credentials.create({publicKey: options}) : await navigator.credentials.get({publicKey: options});
            const response = {clientDataJSON: encode(credential.response.clientDataJSON)};
            if (operation === 'register') {
                response.attestationObject = encode(credential.response.attestationObject);
                response.transports = credential.response.getTransports ? credential.response.getTransports() : [];
            } else {
                response.authenticatorData = encode(credential.response.authenticatorData);
                response.signature = encode(credential.response.signature);
                response.userHandle = credential.response.userHandle ? encode(credential.response.userHandle) : null;
            }
            const result = await post(button.dataset.finish, {operation, name: document.getElementById('um-passkey-name')?.value || '',
                credential: JSON.stringify({id: credential.id, rawId: encode(credential.rawId), type: credential.type, response})});
            const destination = new URL(result.redirect, window.location.href);
            if (destination.origin !== window.location.origin) throw new Error(button.dataset.failed);
            window.location.assign(destination.href);
        } catch (error) { feedback.textContent = error.name === 'NotAllowedError' ? button.dataset.cancelled : (error.message === button.dataset.unsupported ? error.message : button.dataset.failed); }
        finally { button.disabled = false; }
    }));
})();
