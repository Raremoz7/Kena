import type { EventStatus, VenueInfo } from '@/lib/veludo/types';
import type { PriceTier } from './pricing';

export interface PhotoEvent {
    id: number;
    slug: string;
    title: string;
    kicker: string;
    status: EventStatus;
    venue: VenueInfo;
    dateLabel: string;
    timeLabel: string;
    bannerImage?: string;
}

export interface GalleryListItem {
    event: PhotoEvent;
    photoCount: number;
    peopleCount: number;
    covers: string[];
    tiers: PriceTier[];
}

export interface GalleryPhoto {
    id: number;
    thumb: string;
    preview: string;
    width: number;
    height: number;
    group: number | null;
    people: number[];
}

export interface GalleryPerson {
    id: number;
    name: string;
    face: string | null;
    count: number;
}

export interface GalleryGroup {
    id: number;
    name: string;
    count: number;
}

export interface GalleryInfo {
    id: number;
    description: string | null;
    event: PhotoEvent;
    tiers: PriceTier[];
}

export function firstName(name: string): string {
    return name.split(' ')[0] ?? name;
}

/** Busca por nome sem se importar com acento ou maiúscula. */
export function normalize(text: string): string {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}
