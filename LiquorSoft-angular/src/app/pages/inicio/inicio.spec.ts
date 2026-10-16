import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Inicio } from './inicio';
import { initializeComponent, testProviders } from '../../../testing/test-setup';

describe('Inicio', () => {
  let component: Inicio;
  let fixture: ComponentFixture<Inicio>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Inicio],
      providers: testProviders,
    }).compileComponents();

    fixture = TestBed.createComponent(Inicio);
    component = fixture.componentInstance;
    await initializeComponent(fixture);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
