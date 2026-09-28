import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Register } from './register';
import { initializeComponent, testProviders } from '../../../testing/test-setup';

describe('Register', () => {
  let component: Register;
  let fixture: ComponentFixture<Register>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Register],
      providers: testProviders,
    }).compileComponents();

    fixture = TestBed.createComponent(Register);
    component = fixture.componentInstance;
    await initializeComponent(fixture);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
