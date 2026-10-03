'use client';

import { useActionState } from 'react';
import { Send } from 'lucide-react';
import { submitContact, ContactState } from '@/app/contact/action';

export default function ContactForm({ initialSubject }: { initialSubject: string }) {
  const [state, action, pending] = useActionState<ContactState, FormData>(submitContact, null);

  return (
    <form action={action} className="stack">
      {state?.success && <div className="notice">Thanks — your message has been sent.</div>}
      {state?.error && <div className="notice error">{state.error}</div>}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
        <input name="name" placeholder="Your name" required className="input" />
        <input name="email" type="email" placeholder="Email" required className="input" />
      </div>
      <input name="subject" placeholder="Subject" required className="input" defaultValue={initialSubject} />
      <textarea name="message" rows={5} placeholder="Message" required className="input" />
      <button className="button" disabled={pending} style={{ width: '100%' }}><Send className="w-4 h-4" style={{ marginRight: 8 }} />Send Message</button>
    </form>
  );
}
