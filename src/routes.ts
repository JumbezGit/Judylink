import { createBrowserRouter } from "react-router";
import Login from "./pages/Login";
import Plans from "./pages/Plans";

export const router = createBrowserRouter([
  {
    path: "/login",
    Component: Login,
  },
  {
    path: "/staff-login",
    Component: Login,
  },
  {
    path: "*",
    Component: Plans,
  },
]);



