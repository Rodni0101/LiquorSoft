import { Component, ElementRef, ViewChild, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterOutlet } from '@angular/router';
import { CartService } from './cart.service';

interface ChatProduct {
  id: number; name: string; category: string; description: string;
  price: number; stock: number; icon: string;
}

interface ChatMessage { role: 'assistant' | 'user'; text: string; products?: ChatProduct[]; }

@Component({
  imports: [RouterOutlet],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
  protected readonly title = signal('LiquorSoft-angular');
  private readonly http = inject(HttpClient);
  private readonly cart = inject(CartService);
  @ViewChild('chatMessages') private chatMessages?: ElementRef<HTMLElement>;
  protected readonly chatOpen = signal(false);
  protected readonly chatBusy = signal(false);
  protected readonly catalogLoading = signal(false);
  protected readonly catalogLoaded = signal(false);
  protected readonly catalogProducts = signal<ChatProduct[]>([]);
  protected readonly showMoreQuickQuestions = signal(false);
  protected readonly chatInput = signal('');
  protected readonly chatMessagesList = signal<ChatMessage[]>([
    { role: 'assistant', text: '¡Hola! Soy el asistente de LiquorSoft. Puedo buscar productos disponibles, precios, categorías y pedidos de tu cuenta.' },
  ]);
  protected readonly quickQuestions = [
    'Ver productos disponibles',
    '¿Cuántos productos hay?',
    'Buscar whisky',
    'Buscar ron',
    'Buscar vinos',
    'Buscar cervezas',
    'Productos por menos de $100.000',
    'Consultar mis pedidos',
    'Envíos y horarios',
  ];

  protected openChat(): void {
    this.chatOpen.set(true);
    if (this.catalogLoaded() || this.catalogLoading()) return;
    this.catalogLoading.set(true);
    this.http.get<{ products: ChatProduct[] }>('/api/products.php').subscribe({
      next: response => {
        this.catalogProducts.set(response.products);
        this.catalogLoaded.set(true);
        this.catalogLoading.set(false);
      },
      error: () => this.catalogLoading.set(false),
    });
  }

  protected askQuickQuestion(question: string): void {
    this.showMoreQuickQuestions.set(false);
    if (this.answerQuickQuestion(question)) return;
    this.chatInput.set(question);
    this.sendChatMessage();
  }

  private answerQuickQuestion(question: string): boolean {
    if (!this.catalogLoaded()) return false;
    const products = this.catalogProducts();
    const normalized = question.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    let matches: ChatProduct[] | null = null;
    let answer = '';

    if (normalized.includes('cuantos productos')) {
      const available = products.filter(product => product.stock > 0).length;
      answer = `Hay ${products.length} productos activos en el catálogo; ${available} tienen existencias disponibles.`;
      matches = [];
    } else if (normalized.includes('menos de $')) {
      const amount = Number(normalized.match(/\$([\d.]+)/)?.[1]?.replace(/\./g, '') ?? 0);
      matches = products.filter(product => product.stock > 0 && product.price < amount).slice(0, 3);
      answer = matches.length ? 'Estas opciones disponibles están dentro de ese presupuesto:' : 'No encontré productos disponibles dentro de ese presupuesto.';
    } else if (normalized.startsWith('buscar ')) {
      const category = normalized.slice('buscar '.length).trim();
      matches = products.filter(product => product.stock > 0 && this.normalizeCategory(product.category) === this.normalizeCategory(category)).slice(0, 3);
      answer = matches.length ? `Estas opciones de ${category} están disponibles:` : `No encontré productos disponibles en la categoría ${category}.`;
    } else if (normalized.includes('productos disponibles')) {
      matches = products.filter(product => product.stock > 0).slice(0, 3);
      answer = matches.length ? 'Estos productos tienen existencias disponibles:' : 'No hay productos disponibles en este momento.';
    }

    if (matches === null) return false;
    this.chatMessagesList.update(messages => [
      ...messages,
      { role: 'user', text: question },
      { role: 'assistant', text: answer, products: matches },
    ]);
    this.scrollChat();
    return true;
  }

  private normalizeCategory(value: string): string {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/s$/, '');
  }

  protected toggleQuickQuestions(): void {
    this.showMoreQuickQuestions.update(show => !show);
  }

  protected sendChatMessage(event?: Event): void {
    event?.preventDefault();
    const question = this.chatInput().trim();
    if (!question || this.chatBusy()) return;
    this.chatInput.set('');
    this.chatMessagesList.update(messages => [...messages, { role: 'user', text: question }]);
    this.chatBusy.set(true);
    this.http.post<{ answer: string; products: ChatProduct[] }>('/api/chat.php', { message: question }).subscribe({
      next: (response) => {
        this.chatMessagesList.update(messages => [...messages, { role: 'assistant', text: response.answer, products: response.products.filter(product => product.name).slice(0, 3) }]);
        this.chatBusy.set(false);
        this.scrollChat();
      },
      error: (error) => {
        const text = error.status === 429 ? 'Espera un momento antes de enviar otra pregunta.' : error.status === 503 ? 'El catálogo está temporalmente desconectado.' : 'No pude consultar el catálogo. Intenta de nuevo en un momento.';
        this.chatMessagesList.update(messages => [...messages, { role: 'assistant', text }]);
        this.chatBusy.set(false);
        this.scrollChat();
      },
    });
    this.scrollChat();
  }

  protected setChatInput(event: Event): void {
    this.chatInput.set((event.target as HTMLInputElement).value);
  }

  protected formatChatPrice(value: number): string {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0,
    }).format(value);
  }

  protected addChatProduct(product: ChatProduct): void {
    const added = this.cart.add({ id: product.id, name: product.name, price: product.price, icon: product.icon, stock: product.stock });
    this.chatMessagesList.update(messages => [...messages, { role: 'assistant', text: added ? `${product.name} se agregó al carrito.` : `${product.name} no tiene unidades disponibles.` }]);
    this.scrollChat();
  }

  private scrollChat(): void {
    setTimeout(() => { const element = this.chatMessages?.nativeElement; if (element) element.scrollTop = element.scrollHeight; });
  }
}
