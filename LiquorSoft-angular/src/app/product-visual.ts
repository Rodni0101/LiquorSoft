import { Component, Input, OnChanges } from '@angular/core';

type VisualType = 'whisky' | 'ron' | 'vodka' | 'gin' | 'tequila' | 'aguardiente' | 'wine' | 'sparkling' | 'cream' | 'beer' | 'mixer' | 'spirit';

@Component({
  selector: 'app-product-visual',
  standalone: true,
  template: `
    <div class="visual-frame" [attr.data-kind]="visualType">
      @if (imageSource && !imageFailed) {
        <img class="product-photo" [src]="imageSource" [alt]="altText" loading="lazy" decoding="async" (error)="onImageError()" />
      } @else {
        <img class="product-illustration" [src]="fallbackSource" [alt]="altText + ' (ilustración de categoría)'" loading="lazy" decoding="async" />
      }
    </div>
  `,
  styleUrl: './product-visual.css',
})
export class ProductVisual implements OnChanges {
  @Input() category = '';
  @Input() imageUrl: string | null = null;
  @Input() altText = 'Producto';

  protected imageFailed = false;
  protected visualType: VisualType = 'spirit';
  protected fallbackSource = '/assets/products/spirit.svg';
  protected imageSource: string | null = null;

  ngOnChanges(): void {
    this.imageFailed = false;
    this.visualType = this.resolveType(this.category);
    this.fallbackSource = `/assets/products/${this.visualType}.svg`;
    this.imageSource = this.resolveImageSource(this.imageUrl);
  }

  protected onImageError(): void {
    this.imageFailed = true;
  }

  private resolveType(category: string): VisualType {
    const normalized = category.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    if (normalized.includes('whisky') || normalized.includes('whiskey')) return 'whisky';
    if (normalized.includes('ron')) return 'ron';
    if (normalized.includes('vodka')) return 'vodka';
    if (normalized.includes('gin') || normalized.includes('ginebra')) return 'gin';
    if (normalized.includes('tequila') || normalized.includes('mezcal')) return 'tequila';
    if (normalized.includes('aguardiente') || normalized.includes('anis')) return 'aguardiente';
    if (normalized.includes('espumoso') || normalized.includes('champagne') || normalized.includes('champana') || normalized.includes('prosecco')) return 'sparkling';
    if (normalized.includes('vino') || normalized.includes('wine')) return 'wine';
    if (normalized.includes('crema') || normalized.includes('cream')) return 'cream';
    if (normalized.includes('cerveza') || normalized.includes('beer')) return 'beer';
    if (normalized.includes('mixer') || normalized.includes('mezclador')) return 'mixer';
    return 'spirit';
  }

  private resolveImageSource(value: string | null): string | null {
    const source = value?.trim();
    if (!source) return null;
    if (/^https?:\/\//i.test(source)) return source;
    if (/^\/\//.test(source)) return `https:${source}`;
    if (/^[a-z][a-z\d+.-]*:/i.test(source)) return null;
    return `/${source.replace(/^(\.\/)+/, '').replace(/^\/+/, '')}`;
  }
}
