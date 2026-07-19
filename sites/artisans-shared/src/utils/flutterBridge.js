let idCounter = 0;
const pending = new Map();
const watchers = new Map();

function generateId() {
  return `bridge-${++idCounter}-${Date.now()}`;
}

function sendMessage(action, payload) {
  return new Promise((resolve, reject) => {
    const callbackId = generateId();
    pending.set(callbackId, { resolve, reject, action });

    if (typeof FlutterBridge === 'undefined' || !FlutterBridge.postMessage) {
      pending.delete(callbackId);
      reject(new Error('FlutterBridge non disponible'));
      return;
    }

    FlutterBridge.postMessage(JSON.stringify({ action, callbackId, payload }));
  });
}

export function getPosition(options = {}) {
  return sendMessage('getPosition', {
    accuracy: options.accuracy || 'best',
    timeout: options.timeout || 15000,
    maxAccuracy: options.maxAccuracy ?? 20,
  });
}

export function watchPosition(callback, options = {}) {
  const callbackId = generateId();
  watchers.set(callbackId, callback);

  if (typeof FlutterBridge === 'undefined' || !FlutterBridge.postMessage) {
    watchers.delete(callbackId);
    throw new Error('FlutterBridge non disponible');
  }

  FlutterBridge.postMessage(JSON.stringify({
    action: 'watchPosition',
    callbackId,
    payload: {
      accuracy: options.accuracy || 'best',
      distanceFilter: options.distanceFilter ?? 5,
    },
  }));

  return callbackId;
}

export function clearWatch(callbackId) {
  watchers.delete(callbackId);

  if (typeof FlutterBridge !== 'undefined' && FlutterBridge.postMessage) {
    FlutterBridge.postMessage(JSON.stringify({
      action: 'cancelWatchPosition',
      callbackId,
    }));
  }
}

/**
 * Ouvre le sélecteur de photo natif de l'app (galerie ou caméra).
 * Résout { base64, mimeType, name } — rejette avec code 'cancelled' si annulé.
 */
export function pickImage(options = {}) {
  return sendMessage('pickImage', {
    source: options.source || 'gallery',
    quality: options.quality ?? 85,
    maxWidth: options.maxWidth ?? 1600,
  });
}

export function isFlutterApp() {
  return typeof FlutterBridge !== 'undefined' && !!FlutterBridge.postMessage;
}

window.onBiometricResponse = function (callbackId, response) {
  if (!response || typeof response !== 'object') {
    console.error('[flutterBridge] réponse invalide', response);
    return;
  }

  const { success, data, error, message: errorMessage } = response;

  // Watchers
  if (watchers.has(callbackId)) {
    const callback = watchers.get(callbackId);
    if (success) {
      callback(null, data);
    } else {
      callback(new Error(errorMessage || error || 'Erreur GPS'), null);
    }
    return;
  }

  // Pending promises
  if (pending.has(callbackId)) {
    const { resolve, reject } = pending.get(callbackId);
    pending.delete(callbackId);
    if (success) {
      resolve(data);
    } else {
      const err = new Error(errorMessage || 'Erreur FlutterBridge');
      err.code = error;
      reject(err);
    }
  }
};
