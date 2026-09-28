import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Productos } from './productos';
import { initializeComponent, testProviders } from '../../../testing/test-setup';

describe('Productos', () => {
  let component: Productos;
  let fixture: ComponentFixture<Productos>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Productos],
      providers: testProviders,
    }).compileComponents();

    fixture = TestBed.createComponent(Productos);
    component = fixture.componentInstance;
    await initializeComponent(fixture);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
