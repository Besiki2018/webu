import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface SpeechRecognitionAlternativeLike {
    transcript: string;
}

interface SpeechRecognitionResultLike {
    isFinal: boolean;
    length: number;
    [index: number]: SpeechRecognitionAlternativeLike;
}

interface SpeechRecognitionEventLike extends Event {
    resultIndex: number;
    results: ArrayLike<SpeechRecognitionResultLike>;
}

interface SpeechRecognitionErrorEventLike extends Event {
    error: string;
}

interface SpeechRecognitionLike extends EventTarget {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    maxAlternatives: number;
    onresult: ((event: SpeechRecognitionEventLike) => void) | null;
    onerror: ((event: SpeechRecognitionErrorEventLike) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
}

type SpeechRecognitionConstructor = new () => SpeechRecognitionLike;

function getSpeechRecognitionConstructor(): SpeechRecognitionConstructor | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const scopedWindow = window as Window & {
        SpeechRecognition?: SpeechRecognitionConstructor;
        webkitSpeechRecognition?: SpeechRecognitionConstructor;
    };

    return scopedWindow.SpeechRecognition ?? scopedWindow.webkitSpeechRecognition ?? null;
}

function mergeBaseAndTranscript(base: string, transcript: string): string {
    const trimmedBase = base.trim();
    const trimmedTranscript = transcript.trim();

    if (trimmedBase === '') return trimmedTranscript;
    if (trimmedTranscript === '') return trimmedBase;
    return `${trimmedBase} ${trimmedTranscript}`;
}

function readTranscript(results: ArrayLike<SpeechRecognitionResultLike>): string {
    const parts: string[] = [];

    for (let i = 0; i < results.length; i += 1) {
        const result = results[i];
        if (!result || result.length === 0) continue;

        const transcript = result[0]?.transcript?.trim();
        if (!transcript) continue;
        parts.push(transcript);
    }

    return parts.join(' ').trim();
}

export type SpeechToTextErrorCode =
    | 'permission-denied'
    | 'no-speech'
    | 'audio-capture'
    | 'not-supported'
    | 'unknown';

export interface SpeechToTextError {
    code: SpeechToTextErrorCode;
    message: string;
}

function mapSpeechError(error: string): SpeechToTextError {
    switch (error) {
        case 'not-allowed':
        case 'service-not-allowed':
            return {
                code: 'permission-denied',
                message: 'Microphone access was denied.',
            };
        case 'no-speech':
            return {
                code: 'no-speech',
                message: 'No speech was detected.',
            };
        case 'audio-capture':
            return {
                code: 'audio-capture',
                message: 'Microphone is unavailable.',
            };
        default:
            return {
                code: 'unknown',
                message: 'Voice recognition failed. Please try again.',
            };
    }
}

interface UseSpeechToTextOptions {
    lang?: string;
    onTranscript: (text: string) => void;
    onError?: (error: SpeechToTextError) => void;
}

interface UseSpeechToTextResult {
    isSupported: boolean;
    isListening: boolean;
    startListening: (baseText?: string) => void;
    stopListening: () => void;
}

export function useSpeechToText({
    lang = 'ka-GE',
    onTranscript,
    onError,
}: UseSpeechToTextOptions): UseSpeechToTextResult {
    const [isListening, setIsListening] = useState(false);
    const baseTextRef = useRef('');
    const recognitionRef = useRef<SpeechRecognitionLike | null>(null);
    const ctor = useMemo(() => getSpeechRecognitionConstructor(), []);
    const isSupported = ctor !== null;

    const stopListening = useCallback(() => {
        try {
            recognitionRef.current?.stop();
        } catch {
            // No-op: some browsers throw if stop() is called after recognition ended.
        }
        setIsListening(false);
    }, []);

    const startListening = useCallback((baseText = '') => {
        if (!ctor) {
            onError?.({
                code: 'not-supported',
                message: 'Browser does not support speech recognition.',
            });
            return;
        }

        if (isListening) {
            stopListening();
        }

        baseTextRef.current = baseText;

        const recognition = new ctor();
        recognition.lang = lang;
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        recognition.onresult = (event) => {
            const transcript = readTranscript(event.results);
            onTranscript(mergeBaseAndTranscript(baseTextRef.current, transcript));
        };
        recognition.onerror = (event) => {
            setIsListening(false);
            onError?.(mapSpeechError(event.error));
        };
        recognition.onend = () => {
            setIsListening(false);
            recognitionRef.current = null;
        };

        recognitionRef.current = recognition;

        try {
            recognition.start();
            setIsListening(true);
        } catch {
            recognitionRef.current = null;
            setIsListening(false);
            onError?.({
                code: 'unknown',
                message: 'Voice recognition failed. Please try again.',
            });
        }
    }, [ctor, isListening, lang, onError, onTranscript, stopListening]);

    useEffect(() => () => {
        stopListening();
    }, [stopListening]);

    return {
        isSupported,
        isListening,
        startListening,
        stopListening,
    };
}

export default useSpeechToText;
