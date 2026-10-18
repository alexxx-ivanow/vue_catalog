export function getSessid() {
    return window.__BX__?.sessid || '';
}

export function getJsonHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Bitrix-Csrf-Token': getSessid(),
    };
}

export function getReadHeaders() {
    return {
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export async function apiGet(url) {
    const res = await fetch(url, { headers: getReadHeaders() });
    const data = await res.json();
    return { res, data };
}

export async function apiPost(url, body) {
    const payload = { ...(body || {}), sessid: body?.sessid ?? getSessid() };
    const res = await fetch(url, {
        method: 'POST',
        headers: getJsonHeaders(),
        body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data?.sessid) {
        window.__BX__.sessid = data.sessid;
    }
    return { res, data };
}
