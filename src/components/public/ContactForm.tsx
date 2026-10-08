'use client';

import { useActionState } from 'react';
import { Send } from 'lucide-react';
import { submitContact, ContactState } from '@/app/(public)/contact/action';

export default function ContactForm({ initialSubject }: { initialSubject: string }) {
  const [state, action, pending] = useActionState<ContactState, FormData>(submitContact, null);

  return (
    <form action={action} className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label className="label">Your name</label>
          <input name="name" required className="input" placeholder="Full name" />
        </div>
        <div>
          <label className="label">Email</label>
          <input type="email" name="email" required className="input" placeholder="you@example.com" />
        </div>
      </div>
      <div>
        <label className="label">Subject</label>
        <input
          name="subject"
          required
          className="input"
          defaultValue={initialSubject}
          placeholder="How can we help?"
        />
      </div>
      <div>
        <label className="label">Message</label>
        <textarea
          name="message"
          rows={6}
          required
          className="input"
          placeholder="Tell us what you need — dates, vehicle preferences, questions…"
        ></textarea>
      </div>
      <button className="btn-primary justify-center !px-8" disabled={pending}>
        <Send className="w-4 h-4" /> Send Message
      </button>
      {state?.success && (
        <div className="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
          Thanks — your message has been sent.
        </div>
      )}
      {state?.error && (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}
    </form>
  );
}
