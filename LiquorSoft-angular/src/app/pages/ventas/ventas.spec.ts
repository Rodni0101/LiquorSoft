import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Ventas } from './ventas';
import { initializeComponent, testProviders } from '../../../testing/test-setup';

describe('Ventas', () => {
  let component: Ventas;
  let fixture: ComponentFixture<Ventas>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Ventas],
      providers: testProviders,
    }).compileComponents();

    fixture = TestBed.createComponent(Ventas);
    component = fixture.componentInstance;
    await initializeComponent(fixture);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
