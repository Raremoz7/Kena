import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

type Props = Omit<ComponentProps<typeof Input>, 'onInput'> & {
    mask: (value: string) => string;
};

export default function MaskedInput({ mask, ...props }: Props) {
    return (
        <Input
            inputMode="numeric"
            {...props}
            onInput={(e) => {
                e.currentTarget.value = mask(e.currentTarget.value);
            }}
        />
    );
}
