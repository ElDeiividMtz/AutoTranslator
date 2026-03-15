import React, { useState, useRef, useEffect } from 'react';
import { Actions, State, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import { httpErrorToHuman } from '@/api/http';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import ContentBox from '@/components/elements/ContentBox';
import { Button } from '@/components/elements/button/index';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';

// Language code → country code for flag images
const FLAG_COUNTRIES: Record<string, string> = {
    en: 'us',
    es: 'mx',
    pt: 'br',
    fr: 'fr',
    de: 'de',
    it: 'it',
};

function getFlagCountry(langCode: string): string {
    const serverFlags = (window as any).SiteConfiguration?.translatorFlags;
    if (serverFlags?.[langCode]) {
        const flag = serverFlags[langCode];
        if (/^[a-z]{2}$/i.test(flag)) return flag.toLowerCase();
    }
    return FLAG_COUNTRIES[langCode] || langCode;
}

function flagUrl(langCode: string): string {
    return `https://flagcdn.com/24x18/${getFlagCountry(langCode)}.png`;
}

function getLanguages(): Record<string, string> {
    const serverLangs = (window as any).SiteConfiguration?.translatorLanguages;
    if (serverLangs && typeof serverLangs === 'object' && Object.keys(serverLangs).length > 0) {
        return { en: 'English', ...serverLangs };
    }
    return { en: 'English', es: 'Español', pt: 'Português', fr: 'Français', de: 'Deutsch', it: 'Italiano' };
}

const DropdownContainer = styled.div`
    position: relative;
    width: 100%;
`;

const DropdownButton = styled.button<{ $isOpen: boolean }>`
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgb(75, 85, 99);
    border: 2px solid ${(p) => (p.$isOpen ? 'rgb(99, 102, 241)' : 'transparent')};
    border-radius: 0.375rem;
    color: rgb(229, 231, 235);
    font-size: 0.875rem;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.15s;

    &:hover {
        border-color: rgb(99, 102, 241);
    }

    &:focus {
        outline: none;
        border-color: rgb(99, 102, 241);
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3);
    }
`;

const FlagImg = styled.img`
    width: 24px;
    height: 18px;
    border-radius: 2px;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
`;

const ChevronIcon = styled.svg<{ $isOpen: boolean }>`
    margin-left: auto;
    width: 16px;
    height: 16px;
    transition: transform 0.2s;
    transform: rotate(${(p) => (p.$isOpen ? '180deg' : '0deg')});
    color: rgb(156, 163, 175);
`;

const DropdownMenu = styled.div`
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 50;
    background: rgb(55, 65, 81);
    border: 1px solid rgb(75, 85, 99);
    border-radius: 0.5rem;
    padding: 4px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    max-height: 280px;
    overflow-y: auto;
`;

const DropdownItem = styled.button<{ $isActive: boolean }>`
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: none;
    border-radius: 0.375rem;
    background: ${(p) => (p.$isActive ? 'rgba(99, 102, 241, 0.2)' : 'transparent')};
    color: rgb(229, 231, 235);
    font-size: 0.875rem;
    cursor: pointer;
    text-align: left;
    transition: background 0.1s;

    &:hover {
        background: ${(p) => (p.$isActive ? 'rgba(99, 102, 241, 0.3)' : 'rgba(255, 255, 255, 0.08)')};
    }
`;

const LangCode = styled.span`
    margin-left: auto;
    font-size: 0.7rem;
    color: rgb(107, 114, 128);
    text-transform: uppercase;
    letter-spacing: 0.5px;
`;

const LanguageSelector = () => {
    const user = useStoreState((state: State<ApplicationStore>) => state.user.data);
    const { updateUserData } = useStoreActions((actions: Actions<ApplicationStore>) => actions.user);
    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const [language, setLanguage] = useState(user?.language || 'en');
    const [submitting, setSubmitting] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    if (!user) return null;

    const languages = getLanguages();

    const submit = () => {
        clearFlashes('account:language');
        setSubmitting(true);

        http.put('/api/client/extensions/autotranslator/language', { language })
            .then(() => {
                updateUserData({ language });
                try { localStorage.setItem('autotranslator_lang', language); } catch {}
                document.cookie = `autotranslator_lang=${language};path=/;max-age=31536000;SameSite=Lax`;

                addFlash({
                    key: 'account:language',
                    type: 'success',
                    title: 'Success',
                    message: 'Language updated. Reloading...',
                });
                setTimeout(() => window.location.reload(), 800);
            })
            .catch((error) =>
                addFlash({
                    key: 'account:language',
                    type: 'error',
                    title: 'Error',
                    message: httpErrorToHuman(error),
                })
            )
            .then(() => setSubmitting(false));
    };

    return (
        <ContentBox title={'Panel Language'} showFlashes={'account:language'} css={tw`mb-10`}>
            <SpinnerOverlay size={'large'} visible={submitting} />
            <div data-notranslate>
                <label css={tw`block text-xs font-medium text-neutral-200 uppercase mb-2`}>
                    Panel Language
                </label>
                <DropdownContainer ref={containerRef}>
                    <DropdownButton
                        type={'button'}
                        $isOpen={isOpen}
                        onClick={() => setIsOpen(!isOpen)}
                    >
                        <FlagImg src={flagUrl(language)} alt={language} />
                        <span>{languages[language] || language}</span>
                        <ChevronIcon $isOpen={isOpen} viewBox={'0 0 24 24'} fill={'none'} stroke={'currentColor'} strokeWidth={2}>
                            <polyline points={'6 9 12 15 18 9'} />
                        </ChevronIcon>
                    </DropdownButton>
                    {isOpen && (
                        <DropdownMenu>
                            {Object.entries(languages).map(([code, name]) => (
                                <DropdownItem
                                    key={code}
                                    $isActive={code === language}
                                    onClick={() => { setLanguage(code); setIsOpen(false); }}
                                >
                                    <FlagImg src={flagUrl(code)} alt={code} />
                                    <span>{name}</span>
                                    <LangCode>{code}</LangCode>
                                </DropdownItem>
                            ))}
                        </DropdownMenu>
                    )}
                </DropdownContainer>
                <p css={tw`text-xs text-neutral-400 mt-2`}>
                    Select the language you want to use for the panel.
                </p>
            </div>
            <div css={tw`mt-6`}>
                <Button
                    disabled={submitting || language === user.language}
                    onClick={submit}
                >
                    Save Language
                </Button>
            </div>
        </ContentBox>
    );
};

export default LanguageSelector;
