import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * Root layout needs the request path to decide whether to render the
 * public-site chrome (Header/Footer). Vercel supplies x-invoke-path in
 * production but nothing sets it locally — this makes it reliable
 * in every environment.
 */
export function middleware(req: NextRequest) {
  const requestHeaders = new Headers(req.headers);
  requestHeaders.set('x-invoke-path', req.nextUrl.pathname);
  return NextResponse.next({ request: { headers: requestHeaders } });
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
