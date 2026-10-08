import { createClient } from '@supabase/supabase-js';

if (typeof window !== 'undefined') {
  throw new Error('supabase server client must not be imported in the browser');
}

const supabaseUrl = process.env.NEXT_PUBLIC_SUPABASE_URL;
const supabaseServiceKey = process.env.SUPABASE_SERVICE_ROLE_KEY;

if (!supabaseUrl) {
  throw new Error('NEXT_PUBLIC_SUPABASE_URL is not set');
}

if (!supabaseServiceKey) {
  throw new Error('SUPABASE_SERVICE_ROLE_KEY is not set');
}

// Hard timeout on every PostgREST call — a stalled pooler/DB must surface
// as an error (error.tsx retry page), never an endlessly-hanging response.
const fetchWithTimeout: typeof fetch = (input, init) =>
  fetch(input, { ...init, signal: AbortSignal.timeout(15_000) });

export const supabase = createClient(supabaseUrl, supabaseServiceKey, {
  global: { fetch: fetchWithTimeout },
});
