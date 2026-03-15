import React from 'react';

interface Props {
    ns?: string;
    i18nKey?: string;
    values?: Record<string, unknown>;
    children?: React.ReactNode;
}

/**
 * Lightweight replacement for react-i18next <Trans>.
 * Renders the i18nKey as human-readable text (dot → colon, capitalize first word).
 * AutoTranslator handles the actual translation at the DOM level.
 */
export default ({ i18nKey, values, children }: Props) => {
    if (!i18nKey) {
        return <>{children}</>;
    }

    // Convert "server.sftp.write" → "server:sftp.write" (activity event format)
    // Then make it human-readable: "auth.login" → "Signed in"
    let text = i18nKey;

    // Interpolate values like {{name}}, {{count}}, etc.
    if (values) {
        for (const [key, val] of Object.entries(values)) {
            if (val !== null && val !== undefined) {
                text = text.replace(new RegExp(`{{\\s*${key}\\s*}}`, 'g'), String(val));
            }
        }
    }

    // Make activity keys readable: "auth.login" → "Auth: Login"
    // "server.backup.restore-started" → "Server: Backup restore started"
    const parts = text.split('.');
    const readable = parts
        .map((part, i) => {
            const cleaned = part.replace(/-/g, ' ');
            return i === 0
                ? cleaned.charAt(0).toUpperCase() + cleaned.slice(1)
                : cleaned;
        })
        .join(': ');

    return <span>{readable}</span>;
};
