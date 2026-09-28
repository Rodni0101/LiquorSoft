import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';

export const testProviders = [
  provideRouter([]),
  provideHttpClient(),
  provideHttpClientTesting(),
];

export async function initializeComponent<T>(fixture: ComponentFixture<T>): Promise<void> {
  fixture.detectChanges();
  const http = TestBed.inject(HttpTestingController);
  for (const request of http.match(() => true)) {
    request.flush(responseFor(request.request.url));
  }
  await fixture.whenStable();
  fixture.detectChanges();
}

function responseFor(url: string): object {
  if (url.endsWith('/auth/me.php')) return { user: null };
  if (url.endsWith('/public-summary.php')) {
    return { products: 0, monthlySales: 0, availability: 0, units: 0 };
  }
  if (url.endsWith('/dashboard.php')) {
    return {
      stats: {
        totalProducts: 0, availableProducts: 0, outOfStock: 0, totalUnits: 0,
        inventoryValue: 0, lowStock: 0, salesToday: 0, salesThisMonth: 0,
        revenueToday: 0, revenueThisMonth: 0, activeUsers: 0,
      },
      recentProducts: [],
    };
  }
  return { products: [], items: [], movements: [], sales: [] };
}
