import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    alt = 'TB-AI',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/logo.jpeg" alt={alt} {...props} />;
}
