import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Inventario } from './inventario';
import { initializeComponent, testProviders } from '../../../testing/test-setup';

describe('Inventario', () => {
  let component: Inventario;
  let fixture: ComponentFixture<Inventario>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Inventario],
      providers: testProviders,
    }).compileComponents();

    fixture = TestBed.createComponent(Inventario);
    component = fixture.componentInstance;
    await initializeComponent(fixture);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
