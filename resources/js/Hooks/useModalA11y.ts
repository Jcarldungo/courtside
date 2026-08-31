import { RefObject, useEffect, useRef } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * The three things every one of this app's bottom-sheet dialogs needs and
 * none of them had: Escape closes it, Tab cycles within it instead of
 * leaking into the page behind, and focus lands somewhere sensible on open
 * and returns to whatever triggered it on close.
 *
 * Both GuestBookingSheet and AdminActionPanel are the same shape -- a
 * fixed-position dialog mounted conditionally on some selected value -- so
 * this is shared rather than reimplemented per component.
 */
export function useModalA11y<T extends HTMLElement>(
    isOpen: boolean,
    onClose: () => void,
    initialFocusRef?: RefObject<HTMLElement | null>,
) {
    const containerRef = useRef<T>(null);
    const triggerRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        triggerRef.current = document.activeElement as HTMLElement;
        const container = containerRef.current;

        const focusInitial = () => {
            if (initialFocusRef?.current) {
                initialFocusRef.current.focus();
                return;
            }
            container?.querySelector<HTMLElement>(FOCUSABLE)?.focus();
        };

        // A tick after mount: the element to focus may not exist on the very
        // first render pass (e.g. it depends on which branch of the panel
        // just mounted).
        const raf = requestAnimationFrame(focusInitial);

        function handleKeyDown(e: KeyboardEvent) {
            if (e.key === 'Escape') {
                onClose();
                return;
            }

            if (e.key !== 'Tab' || !container) {
                return;
            }

            const items = Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
                (el) => el.offsetParent !== null,
            );

            if (items.length === 0) {
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            cancelAnimationFrame(raf);
            document.removeEventListener('keydown', handleKeyDown);
            triggerRef.current?.focus();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isOpen]);

    return containerRef;
}
