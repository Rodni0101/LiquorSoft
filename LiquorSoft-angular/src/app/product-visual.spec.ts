import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ProductVisual } from './product-visual';

describe('ProductVisual', () => {
  let fixture: ComponentFixture<ProductVisual>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({ imports: [ProductVisual] }).compileComponents();
    fixture = TestBed.createComponent(ProductVisual);
  });

  it('selects the category illustration when no product photo is configured', () => {
    fixture.componentRef.setInput('category', 'Aguardiente');
    fixture.componentRef.setInput('altText', 'Aguardiente de prueba');
    fixture.detectChanges();

    const image = fixture.nativeElement.querySelector('img') as HTMLImageElement;
    expect(image.getAttribute('src')).toContain('/assets/products/aguardiente.svg');
    expect(image.alt).toContain('Aguardiente de prueba');
    expect(image.alt).toContain('ilustración de categoría');
  });

  it('has a distinct fallback for each supported drink family', () => {
    const cases = [
      ['Whisky', 'whisky'], ['Ron', 'ron'], ['Vodka', 'vodka'], ['Ginebra', 'gin'],
      ['Mezcal', 'tequila'], ['Aguardiente', 'aguardiente'], ['Vinos', 'wine'],
      ['Espumosos', 'sparkling'], ['Cremas', 'cream'], ['Cervezas', 'beer'],
      ['Mixers', 'mixer'], ['Sin categoría', 'spirit'],
    ];
    for (const [category, asset] of cases) {
      fixture.componentRef.setInput('category', category);
      fixture.detectChanges();
      const image = fixture.nativeElement.querySelector('img') as HTMLImageElement;
      expect(image.getAttribute('src')).toContain(`/assets/products/${asset}.svg`);
    }
  });

  it('uses a configured product photo and falls back when it fails', () => {
    fixture.componentRef.setInput('category', 'Vinos');
    fixture.componentRef.setInput('imageUrl', '/uploads/bottle.webp');
    fixture.componentRef.setInput('altText', 'Reserva');
    fixture.detectChanges();

    let image = fixture.nativeElement.querySelector('img') as HTMLImageElement;
    expect(image.classList.contains('product-photo')).toBe(true);
    expect(image.getAttribute('src')).toBe('/uploads/bottle.webp');

    image.dispatchEvent(new Event('error'));
    fixture.detectChanges();
    image = fixture.nativeElement.querySelector('img') as HTMLImageElement;
    expect(image.classList.contains('product-illustration')).toBe(true);
    expect(image.getAttribute('src')).toContain('/assets/products/wine.svg');
  });

  it('uses the category fallback for an unsupported image protocol', () => {
    fixture.componentRef.setInput('category', 'Vodka');
    fixture.componentRef.setInput('imageUrl', 'javascript:alert(1)');
    fixture.detectChanges();

    const image = fixture.nativeElement.querySelector('img') as HTMLImageElement;
    expect(image.classList.contains('product-illustration')).toBe(true);
    expect(image.getAttribute('src')).toContain('/assets/products/vodka.svg');
  });
});
