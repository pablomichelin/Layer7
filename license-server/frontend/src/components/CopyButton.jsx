import { useState } from 'react';

export default function CopyButton({ text, label = 'Copiar', className = '' }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy(event) {
    event?.stopPropagation?.();
    if (!text) {
      return;
    }

    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // Fallback for older browsers / insecure context
      const input = document.createElement('textarea');
      input.value = text;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    }
  }

  return (
    <button
      type="button"
      onClick={handleCopy}
      className={`text-xs text-brand-600 hover:underline ${className}`}
      title={text}
    >
      {copied ? 'Copiado' : label}
    </button>
  );
}
