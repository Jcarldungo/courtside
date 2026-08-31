/**
 * A small, hand-picked inline SVG set instead of an icon library dependency.
 * This whole app needs about a dozen glyphs; pulling in a full icon package
 * for that is bytes a low-end Android on mobile data does not need to spend.
 */
import { SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement>;

const base = (props: IconProps) => ({
    width: 24,
    height: 24,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
    ...props,
});

export function PhoneIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L14 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 4 6a2 2 0 0 1 0-2z" />
        </svg>
    );
}

export function PinIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 21s7-6.6 7-12a7 7 0 1 0-14 0c0 5.4 7 12 7 12z" />
            <circle cx="12" cy="9" r="2.5" />
        </svg>
    );
}

export function ClockIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3.5 2" />
        </svg>
    );
}

export function ChevronRightIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m9 6 6 6-6 6" />
        </svg>
    );
}

export function ChevronLeftIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m15 6-6 6 6 6" />
        </svg>
    );
}

export function CheckCircleIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12.5 2.5 2.5 5-5.5" />
        </svg>
    );
}

export function AlertIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 3 2 20h20L12 3z" />
            <path d="M12 10v4" />
            <circle cx="12" cy="17.2" r="0.4" fill="currentColor" />
        </svg>
    );
}

export function UploadIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 15V4M8 8l4-4 4 4" />
            <path d="M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3" />
        </svg>
    );
}

export function WrenchIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2-2z" />
        </svg>
    );
}

export function XIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M6 6l12 12M18 6 6 18" />
        </svg>
    );
}

export function MenuIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
    );
}
