export function maskPhone(value: string): string {
    const d = value.replace(/\D/g, '').slice(0, 11);

    if (d.length === 0) {
return '';
}

    if (d.length <= 2) {
return `(${d}`;
}

    if (d.length <= 6) {
return `(${d.slice(0, 2)}) ${d.slice(2)}`;
}

    if (d.length <= 10) {
return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
}

    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

export function maskCpf(value: string): string {
    return value
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}
