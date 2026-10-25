import { Outlet } from "react-router";
import Header from "../components/Header";
import Footer from "../components/Footer";
import { ScrollToTopOnNav, ScrollToTopButton } from "../components/ScrollToTop";

export default function RootLayout() {
  return (
    <>
      <ScrollToTopOnNav />
      <Header />
      <main>
        <Outlet />
      </main>
      <Footer />
      <ScrollToTopButton />
    </>
  );
}



